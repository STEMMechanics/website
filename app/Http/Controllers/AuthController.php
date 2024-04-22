<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\LoginLink;
use App\Mail\RegisterLink;
use App\Models\EmailSubscriptions;
use App\Models\EmailUpdate;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function showLogin(Request $request) {
        if (auth()->check()) {
//            return redirect()->route('dashboard');
            return redirect()->action([HomeController::class, 'index']);
        }

        $token = $request->query('token');
        if ($token) {
            return $this->tokenLogin($token);
        }

        return view('auth.login');
    }

    public function tokenLogin($token)
    {
        $loginToken = DB::table('login_tokens')->where('token', $token)->first();

        if ($loginToken) {
            $user = User::where('email', $loginToken->email)->first();
            $intended_url = $loginToken->intended_url;

            DB::table('login_tokens')->where('token', $token)->delete();

            if ($user) {
                Auth::login($user);

                $user->markEmailAsVerified();
                DB::table('login_tokens')->where('token', $token)->delete();

                session()->flash('message', 'You have been logged in');
                session()->flash('message-title', 'Logged in');
                session()->flash('message-type', 'success');

                if($intended_url) {
                    return redirect($intended_url);
                }

                return redirect()->action([HomeController::class, 'index']);
            }
        }

        session()->flash('message', 'That token has expired or is invalid');
        session()->flash('message-title', 'Log in failed');
        session()->flash('message-type', 'danger');
        return view('auth.login');
    }

    public function postLogin(Request $request) {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        $user = User::where('email', $request->email)->first();
        if($user) {
            $token = $user->createLoginToken(session()->pull('url.intended', null));
            dispatch(new SendEmail($user->email, new LoginLink($token, $user->getName(), $user->email)))->onQueue('mail');

            return view('auth.login-link');
        }

        session()->flash('status', 'not-found');
        return view('auth.login');
    }

    public function logout() {
        auth()->logout();

        session()->flash('message', 'You have been logged out');
        session()->flash('message-title', 'Logged out');
        session()->flash('message-type', 'warning');
        return redirect()->route('index');
    }

    public function showRegister(Request $request) {
        if (auth()->check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function postRegister(Request $request) {
        $request->validate([
            'email' => 'required|email',
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid')
        ]);

        $user = User::where('email', $request->email)->first();
        if($user) {
            if($user->email_verified_at !== null) {
                return redirect()->back()->withInput()->withErrors([
                    'email' => __('validation.custom_messages.email_exists'),
                ]);
            }
        } else {
            $firstname = explode('@', $request->email)[0];

            $user = User::create([
                'firstname'  => $firstname,
                'email' => $request->email,
            ]);

            EmailUpdate::where('email', $request->email)->delete();
        }

        $token = $user->createLoginToken(session()->pull('url.intended', null));
        dispatch(new SendEmail($user->email, new RegisterLink($token, $user->getName(), $user->email)))->onQueue('mail');

        return view('auth.login-link');
    }

    public function updateEmail(Request $request)
    {
        $token = $request->query('token');
        $emailUpdate = EmailUpdate::where('token', $token)->first();
        if($emailUpdate && $emailUpdate->user) {
            $emailUpdate->user->email = $emailUpdate->email;
            $emailUpdate->user->email_verified_at = now();
            $emailUpdate->user->save();
            $emailUpdate->delete();

            session()->flash('message', 'Your email has been updated');
            session()->flash('message-title', 'Email updated');
            session()->flash('message-type', 'success');
            return redirect()->route('index');
        }

        session()->flash('message', 'That token has expired or is invalid');
        session()->flash('message-title', 'Email update failed');
        session()->flash('message-type', 'danger');
        return redirect()->route('index');
    }
}
