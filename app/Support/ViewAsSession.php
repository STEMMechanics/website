<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ViewAsSession
{
    public const SESSION_KEY = 'view_as';

    public function originalAdmin(Request $request): ?User
    {
        $state = $request->session()->get(self::SESSION_KEY);
        $guard = Auth::guard();

        if (is_array($state)) {
            $adminId = trim((string) ($state['admin_id'] ?? ''));
            $authenticatedId = trim((string) $request->session()->get($guard->getName(), ''));

            if ($adminId === '' || $authenticatedId === '' || ! hash_equals($adminId, $authenticatedId)) {
                return null;
            }

            $admin = User::query()->find($adminId);

            return $admin instanceof User && $admin->isAdmin() && ! $admin->isAnonymized()
                ? $admin
                : null;
        }

        $user = $request->user();

        return $user instanceof User && $user->isAdmin() && ! $user->isAnonymized()
            ? $user
            : null;
    }

    public function clear(Request $request): void
    {
        $request->session()->forget(self::SESSION_KEY);
    }
}
