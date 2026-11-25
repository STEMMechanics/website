<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\ViewAsSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ViewAsController extends Controller
{
    public function __construct(private readonly ViewAsSession $viewAs) {}

    public function suggestions(Request $request): JsonResponse
    {
        $admin = $this->viewAs->originalAdmin($request);
        abort_unless($admin instanceof User, 403);

        $query = mb_strtolower(trim((string) $request->query('q', '')));
        if (mb_strlen($query) < 2) {
            return response()->json(['users' => []]);
        }

        $terms = array_values(array_filter(preg_split('/\s+/u', $query) ?: []));
        $users = User::query()
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->whereNull('anonymized_at')
            ->where('id', '<>', (string) $admin->getKey())
            ->where(function ($searchQuery) use ($terms): void {
                foreach ($terms as $term) {
                    $searchQuery->where(function ($termQuery) use ($term): void {
                        $pattern = '%'.$term.'%';
                        $termQuery->whereRaw('LOWER(email) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(firstname) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(surname) LIKE ?', [$pattern]);
                    });
                }
            })
            ->orderBy('email')
            ->limit(8)
            ->get(['id', 'firstname', 'surname', 'email'])
            ->map(fn (User $user): array => [
                'email' => (string) $user->email,
                'name' => trim(implode(' ', array_filter([(string) $user->firstname, (string) $user->surname]))),
            ])
            ->values();

        return response()->json(['users' => $users]);
    }

    public function start(Request $request): RedirectResponse
    {
        $admin = $this->viewAs->originalAdmin($request);
        abort_unless($admin instanceof User, 403);

        $validated = $request->validateWithBag('viewAs', [
            'email' => ['required', 'email', 'max:255'],
        ]);

        $email = mb_strtolower(trim((string) $validated['email']));
        $target = User::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->whereNull('anonymized_at')
            ->first();

        if (! $target instanceof User) {
            return redirect()->back()
                ->withErrors(['email' => 'No active user was found with that email address.'], 'viewAs')
                ->withInput();
        }

        if ((string) $target->getKey() === (string) $admin->getKey()) {
            return redirect()->back()
                ->withErrors(['email' => 'You are already logged in as this user.'], 'viewAs')
                ->withInput();
        }

        $request->session()->put(ViewAsSession::SESSION_KEY, [
            'admin_id' => (string) $admin->getKey(),
            'user_id' => (string) $target->getKey(),
        ]);
        $request->session()->regenerate();

        return redirect()->route('account.show');
    }

    public function exit(Request $request): RedirectResponse
    {
        $admin = $this->viewAs->originalAdmin($request);
        abort_unless($admin instanceof User && $request->session()->has(ViewAsSession::SESSION_KEY), 403);

        $this->viewAs->clear($request);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')
            ->with('message', 'You’ve exited View As mode and returned to your admin account.')
            ->with('message-type', 'info');
    }
}
