<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UserEmailUpdateConfirm;
use App\Mail\UserLogin;
use App\Mail\UserLoginBackupCode;
use App\Mail\UserRegister;
use App\Mail\UserWelcome;
use App\Models\Token;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthController extends Controller
{
    /**
     * Show the login form or if token present, process the login
     *
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function showLogin(Request $request): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->action([HomeController::class, 'index']);
        }

        $token = $request->query('token');
        if ($token) {
            return $this->LoginByToken($token);
        }

        return view('auth.login');
    }

    /**
     * Process the login form
     *
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function postLogin(Request $request): View|RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'captcha' => 'required_captcha',
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        $forceEmailLogin = false;

        if($request->has('code')) {
            $user = User::where('email', $request->email)->whereNotNull('email_verified_at')->first();
            if($user) {
                $tfa = AccountController::getTFAInstance();
                if ($request->code && $tfa->verifyCode($user->tfa_secret, $request->code, 4)) {
                    $data = ['url' => session()->pull('url.intended', null)];
                    return $this->loginByUser($user, $data);
                }
            }

            return view('auth.login-2fa', ['email' => $request->email])->withErrors([
                'code' => 'The 2FA code is not valid',
            ]);
        }

        if($request->has('backup_code')) {
            $user = User::where('email', $request->email)->whereNotNull('email_verified_at')->first();
            if($user) {
                if($user->verifyBackupCode($request->backup_code)) {
                    $data = ['url' => session()->pull('url.intended', null)];

                    dispatch(new SendEmail($user->email, new UserLoginBackupCode($user->email)))->onQueue('mail');

                    return $this->loginByUser($user, $data);
                }
            }

            return view('auth.login-2fa', ['email' => $request->email, 'method' => 'backup'])->withErrors([
                'backup_code' => 'The backup code is not valid',
            ]);
        }

        if($request->has('method')) {
            if($request->get('method') === 'email') {
                $forceEmailLogin = true;
            } else {
                abort(404);
            }
        }

        $user = User::where('email', $request->email)->whereNotNull('email_verified_at')->first();
        if ($user) {
            if (!$forceEmailLogin && $user->tfa_secret !== null) {
                return view('auth.login-2fa', ['user' => $user]);
            }

            $token = $user->tokens()->create([
                'type' => 'login',
                'data' => ['url' => session()->pull('url.intended', null)],
            ]);

            dispatch(new SendEmail($user->email, new UserLogin($token->id, $user->getName(), $user->email)))->onQueue('mail');
            return view('auth.login-link');
        }

        session()->flash('status', 'not-found');
        return view('auth.login');
    }


    /**
     * Process the login by token
     *
     * @param string $tokenStr
     * @return View|RedirectResponse
     */
    public function loginByToken(string $tokenStr): View|RedirectResponse
    {
        $token = Token::where('id', $tokenStr)
            ->where('type', 'login')
            ->where('expires_at', '>', now())
            ->first();

        if ($token) {
            $user = $token->user;
            if($user) {
                $token->delete();
                return $this->loginByUser($user, $token->data);
            }
        }

        session()->flash('message', 'That token has expired or is invalid');
        session()->flash('message-title', 'Log in failed');
        session()->flash('message-type', 'danger');
        return view('auth.login');
    }

    /**
     * Process the login by user
     *
     * @param User $user
     * @param array $data
     * @return RedirectResponse
     */
    public function loginByUser(User $user, array $data = [])
    {
        $url = null;
        if($data && isset($data->url) && $data->url) {
            $url = $data->url;
        }

        Auth::login($user);

        session()->flash('message', 'You have been logged in');
        session()->flash('message-title', 'Logged in');
        session()->flash('message-type', 'success');

        if($url) {
            return redirect($url);
        }

        return redirect()->action([HomeController::class, 'index']);
    }

    /**
     * Process the user logout
     *
     * @return RedirectResponse
     */
    public function logout(): RedirectResponse
    {
        auth()->logout();

        session()->flash('message', 'You have been logged out');
        session()->flash('message-title', 'Logged out');
        session()->flash('message-type', 'warning');
        return redirect()->route('index');
    }

    /**
     * Show the registration form or if token present, process the registration
     *
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function showRegister(Request $request): View|RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->route('index');
        }

        $tokenStr = $request->query('token');
        if ($tokenStr) {
            $token = Token::where('id', $tokenStr)
                ->where('type', 'register')
                ->where('expires_at', '>', now())
                ->first();

            if ($token) {
                $user = $token->user;
                if ($user) {
                    $user->email_verified_at = now();
                    $user->save();

                    $user->tokens()->where('type', 'register')->delete();

                    dispatch(new SendEmail($user->email, new UserWelcome($user->email)))->onQueue('mail');

                    $this->loginByUser($user);
                    return redirect()->route('index');
                }
            }

            session()->flash('message', 'That token has expired or is invalid');
            session()->flash('message-title', 'Registration failed');
            session()->flash('message-type', 'danger');
        }


        return view('auth.register');
    }

    /**
     * Process the registration form
     *
     * @param Request $request
     * @return View|RedirectResponse
     */
    public function postRegister(Request $request): View|RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'captcha' => 'required_captcha',
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid')
        ]);

        $key = $request->get('name', '');
        $passHoneypot = ($key === 'AC9E94587F163AD93174FBF3DFDF9645B886960F2F8DD6D60F81CDB6DCDA3BC33');

        $user = User::where('email', $request->email)->first();
        if($user) {
            if($user->email_verified_at !== null) {
                return redirect()->back()->withInput()->withErrors([
                    'email' => __('validation.custom_messages.email_exists'),
                ]);
            }
        } else if($passHoneypot) {
            $user = User::create([
                'email' => $request->email,
            ]);
        }

        if($passHoneypot) {
            Log::channel('honeypot')->info('Valid key used for registration using email: ' . $request->email . ', ip address: ' . $request->ip() . ', user agent: ' . $request->userAgent());
            $user->tokens()->where('type', 'register')->delete();
            $token = $user->tokens()->create([
                'type' => 'register',
                'data' => ['url' => session()->pull('url.intended', null)],
            ]);

            dispatch(new SendEmail($user->email, new UserRegister($token->id, $user->email)))->onQueue('mail');
        } else {
            Log::channel('honeypot')->info('Invalid key used for registration using email: ' . $request->email . ', ip address: ' . $request->ip() . ', user agent: ' . $request->userAgent() . ', key: ' . $key);
        }

        return view('auth.register-link');
    }

    /**
     * Confirm the user email update.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function updateEmail(Request $request): RedirectResponse
    {
        $tokenStr = $request->query('token');

        $token = Token::where('id', $tokenStr)
            ->where('type', 'email-update')
            ->where('expires_at', '>', now())
            ->first();

        if($token && $token->user) {
            if($token->data && isset($token->data['email'])) {
                $user = $token->user;
                $user->email = $token->data['email'];
                $user->email_verified_at = now();
                $user->save();

                $user->tokens()->where('type', 'email-update')->delete();

                session()->flash('message', 'Your email has been updated');
                session()->flash('message-title', 'Email updated');
                session()->flash('message-type', 'success');

                dispatch(new SendEmail($user->email, new UserEmailUpdateConfirm($user->email)))->onQueue('mail');

                return redirect()->route('index');
            }
        }

        session()->flash('message', 'That token has expired or is invalid');
        session()->flash('message-title', 'Email update failed');
        session()->flash('message-type', 'danger');

        return redirect()->route('index');
    }
}
