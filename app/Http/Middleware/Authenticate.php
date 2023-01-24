<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;

class Authenticate extends Middleware
{
    /**
     * Get the path the user should be redirected to when they are not authenticated.
     *
     * @param  mixed $request Request.
     * @return string|null
     */
    protected function redirectTo(mixed $request)
    {
        if ($request->expectsJson() === false) {
            return route('login');
        }
    }
}
