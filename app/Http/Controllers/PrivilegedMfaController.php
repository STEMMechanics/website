<?php

namespace App\Http\Controllers;

use App\Http\Middleware\RequirePrivilegedMfa;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class PrivilegedMfaController extends Controller
{
    public function show(Request $request): \Illuminate\View\View|\Illuminate\Http\RedirectResponse
    {
        if (! $request->user()?->tfa_secret) {
            return redirect()->route('account.show');
        }

        return view('auth.privileged-mfa');
    }

    public function verify(Request $request): \Illuminate\Http\RedirectResponse
    {
        $data = $request->validate(['code' => 'required|string|max:100']);
        $user = $request->user();
        abort_unless($user && $user->tfa_secret, 403);
        $code = trim($data['code']);
        $valid = preg_match('/\A[0-9]{6}\z/', $code)
            ? AccountController::verifyTfaCode((string) $user->tfa_secret, $code)
                && Cache::add('mfa-used:'.hash('sha256', $user->id.'|'.$code), true, 300)
            : $user->verifyBackupCode($code);
        if (! $valid) {
            return back()->withErrors(['code' => 'The verification code is not valid.']);
        }
        $request->session()->regenerate();
        RequirePrivilegedMfa::confirm($request, $user);

        return redirect()->route('admin.dashboard');
    }
}
