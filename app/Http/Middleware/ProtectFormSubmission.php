<?php

namespace App\Http\Middleware;

use App\Support\FormGuard;
use Closure;
use Illuminate\Http\Request;

class ProtectFormSubmission
{
    public function __construct(
        private readonly FormGuard $formGuard
    ) {}

    public function handle(Request $request, Closure $next, string $form)
    {
        $this->formGuard->ensureValid($request, $form);

        return $next($request);
    }
}
