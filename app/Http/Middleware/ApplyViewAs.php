<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\ViewAsSession;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApplyViewAs
{
    public function __construct(private readonly ViewAsSession $viewAs) {}

    public function handle(Request $request, Closure $next): Response
    {
        $state = $request->session()->get(ViewAsSession::SESSION_KEY);
        if (! is_array($state)) {
            return $next($request);
        }

        $admin = $this->viewAs->originalAdmin($request);
        $targetId = trim((string) ($state['user_id'] ?? ''));
        $target = $targetId !== '' ? User::query()->find($targetId) : null;

        if (! $admin instanceof User || ! $target instanceof User || $target->isAnonymized()) {
            $this->viewAs->clear($request);

            return $next($request);
        }

        Auth::guard()->setUser($target);
        $request->setUserResolver(static fn ($guard = null): User => $target);
        $request->attributes->set('view_as.admin', $admin);

        return $next($request);
    }
}
