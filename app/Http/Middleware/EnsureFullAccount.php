<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFullAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || $user->canPurchaseOrBook()) {
            return $next($request);
        }

        $message = 'Child accounts can use discussions only. Purchases, tickets, invoices, and order features require a full account.';

        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'message' => $message,
            ], 403);
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Full account required');
        session()->flash('message-type', 'warning');

        return redirect()->route('account.show');
    }
}
