<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\RefreshToken as PassportRefreshToken;
use Laravel\Passport\Token as PassportToken;

class ConnectedAppController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('account.oauth-apps', [
            'user' => $user,
            'connectedApps' => $this->buildConnectedApps($user),
        ]);
    }

    public function destroy(Request $request, PassportClient $client): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $revokedCount = $this->revokeClientTokens($user, $client);

        if ($revokedCount === 0) {
            abort(404);
        }

        session()->flash('message', 'Access for '.$client->name.' has been revoked.');
        session()->flash('message-title', 'Connected apps');
        session()->flash('message-type', 'success');

        return redirect()->route('account.oauth-apps.index');
    }

    public function destroyAll(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $revokedCount = $this->revokeAllTokens($user);

        session()->flash(
            'message',
            $revokedCount > 0
                ? 'All connected app access has been revoked.'
                : 'No active connected app access was found.'
        );
        session()->flash('message-title', 'Connected apps');
        session()->flash('message-type', $revokedCount > 0 ? 'success' : 'info');

        return redirect()->route('account.oauth-apps.index');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{
     *     client: \Laravel\Passport\Client,
     *     scopes: list<string>,
     *     last_authorized_at: ?string,
     *     token_count: int
     * }>
     */
    private function buildConnectedApps(User $user): Collection
    {
        return PassportToken::query()
            ->with('client')
            ->where('user_id', (string) $user->id)
            ->where('revoked', false)
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->whereHas('client', fn ($query) => $query
                ->where('revoked', false)
                ->whereJsonContains('grant_types', 'authorization_code'))
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('client_id')
            ->map(function (Collection $tokens): ?array {
                $client = $tokens->first()?->client;

                if (! $client instanceof PassportClient) {
                    return null;
                }

                return [
                    'client' => $client,
                    'scopes' => $tokens
                        ->flatMap(fn (PassportToken $token): array => is_array($token->scopes) ? $token->scopes : [])
                        ->filter()
                        ->unique()
                        ->values()
                        ->all(),
                    'last_authorized_at' => optional($tokens->first()?->created_at)->toDateTimeString(),
                    'token_count' => $tokens->count(),
                ];
            })
            ->filter()
            ->values();
    }

    private function revokeClientTokens(User $user, PassportClient $client): int
    {
        $tokens = PassportToken::query()
            ->with('refreshToken')
            ->where('user_id', (string) $user->id)
            ->where('client_id', $client->id)
            ->where('revoked', false)
            ->whereHas('client', fn ($query) => $query->whereJsonContains('grant_types', 'authorization_code'))
            ->get();

        return $this->revokeTokens($tokens);
    }

    private function revokeAllTokens(User $user): int
    {
        $tokens = PassportToken::query()
            ->with('refreshToken')
            ->where('user_id', (string) $user->id)
            ->where('revoked', false)
            ->whereHas('client', fn ($query) => $query->whereJsonContains('grant_types', 'authorization_code'))
            ->get();

        return $this->revokeTokens($tokens);
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \Laravel\Passport\Token>  $tokens
     */
    private function revokeTokens(Collection $tokens): int
    {
        $revokedCount = 0;

        foreach ($tokens as $token) {
            if ($token->refreshToken instanceof PassportRefreshToken) {
                $token->refreshToken->revoke();
            }

            if ($token->revoke()) {
                $revokedCount++;
            }
        }

        return $revokedCount;
    }
}
