<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Passport\Client as PassportClient;
use Laravel\Passport\ClientRepository;

class OAuthClientController extends Controller
{
    public function index(): View
    {
        $clients = PassportClient::query()
            ->with(['owner'])
            ->withCount(['tokens as active_token_count' => fn ($query) => $query->where('revoked', false)])
            ->orderBy('revoked')
            ->orderBy('name')
            ->get();

        $loginClients = $clients->filter(fn (PassportClient $client): bool => in_array('authorization_code', $client->grant_types, true));
        $internalClients = $clients->reject(fn (PassportClient $client): bool => in_array('authorization_code', $client->grant_types, true));

        return view('admin.oauth-clients.index', [
            'clients' => $loginClients->values(),
            'internalClients' => $internalClients->values(),
            'recentClientId' => session('oauth_client_id'),
            'recentClientSecret' => session('oauth_client_secret'),
        ]);
    }

    public function create(): View
    {
        return view('admin.oauth-clients.create', [
            'openidScopes' => array_keys(config('openid.passport.tokens_can', [])),
            'openidDiscoveryUrl' => route('openid.discovery'),
            'openidJwksUrl' => route('openid.jwks'),
            'openidUserinfoUrl' => route('openid.userinfo'),
            'openidLogoUrl' => 'https://www.stemmechanics.com.au/toolbox-sm.png',
        ]);
    }

    public function store(Request $request, ClientRepository $clients): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'string', 'max:5000'],
            'public_client' => ['nullable', 'boolean'],
            'enable_device_flow' => ['nullable', 'boolean'],
        ]);

        $redirectUris = $this->parseRedirectUris((string) $validated['redirect_uris']);

        if ($redirectUris === []) {
            throw ValidationException::withMessages([
                'redirect_uris' => 'Enter at least one redirect URI.',
            ]);
        }

        foreach ($redirectUris as $redirectUri) {
            if (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages([
                    'redirect_uris' => "The redirect URI [{$redirectUri}] is not valid.",
                ]);
            }
        }

        $client = $clients->createAuthorizationCodeGrantClient(
            (string) $validated['name'],
            $redirectUris,
            ! $request->boolean('public_client'),
            null,
            $request->boolean('enable_device_flow')
        );

        session()->flash('message', 'OAuth client created.');
        session()->flash('message-title', 'OAuth clients');
        session()->flash('message-type', 'success');
        session()->flash('oauth_client_id', (string) $client->id);
        session()->flash('oauth_client_secret', $client->plainSecret);

        return redirect()->route('admin.oauth-clients.index');
    }

    public function edit(PassportClient $client): View
    {
        return view('admin.oauth-clients.edit', [
            'client' => $client->load(['owner']),
        ]);
    }

    public function update(Request $request, PassportClient $client, ClientRepository $clients): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'redirect_uris' => ['required', 'string', 'max:5000'],
        ]);

        $redirectUris = $this->parseRedirectUris((string) $validated['redirect_uris']);

        if ($redirectUris === []) {
            throw ValidationException::withMessages([
                'redirect_uris' => 'Enter at least one redirect URI.',
            ]);
        }

        foreach ($redirectUris as $redirectUri) {
            if (filter_var($redirectUri, FILTER_VALIDATE_URL) === false) {
                throw ValidationException::withMessages([
                    'redirect_uris' => "The redirect URI [{$redirectUri}] is not valid.",
                ]);
            }
        }

        $clients->update($client, (string) $validated['name'], $redirectUris);

        session()->flash('message', 'OAuth client updated.');
        session()->flash('message-title', (string) $validated['name']);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.oauth-clients.edit', $client);
    }

    public function rotateSecret(PassportClient $client, ClientRepository $clients): RedirectResponse
    {
        if ($client->revoked) {
            abort(404);
        }

        $clients->regenerateSecret($client);

        session()->flash('message', 'OAuth client secret rotated.');
        session()->flash('message-title', $client->name);
        session()->flash('message-type', 'success');
        session()->flash('oauth_client_id', (string) $client->id);
        session()->flash('oauth_client_secret', $client->plainSecret);

        return redirect()->route('admin.oauth-clients.index');
    }

    public function destroy(PassportClient $client, ClientRepository $clients): RedirectResponse
    {
        $clients->delete($client);

        session()->flash('message', 'OAuth client revoked.');
        session()->flash('message-title', $client->name);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.oauth-clients.index');
    }

    public function purge(PassportClient $client): RedirectResponse
    {
        if (! $client->revoked) {
            abort(409, 'Revoke the client before deleting it permanently.');
        }

        $client->delete();

        session()->flash('message', 'OAuth client deleted permanently.');
        session()->flash('message-title', $client->name);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.oauth-clients.index');
    }

    /**
     * @return list<string>
     */
    private function parseRedirectUris(string $redirectUris): array
    {
        return collect(preg_split('/[\r\n,]+/', $redirectUris) ?: [])
            ->map(fn (mixed $uri) => trim((string) $uri))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
