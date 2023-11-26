<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmailJob;
use App\Mail\UserEmailVerify;
use App\Models\User;
use App\Models\VerificationCode;
use Illuminate\Http\Request;
use BayAreaWebPro\MultiStepForms\MultiStepForm as Form;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    // Show Register Form
    public function register(Request $request) {
        if(auth()->check()) {
            return view('home');
        }

        $form = Form::make('users.register')
            ->namespaced('register')
            ->canNavigateBack(true)
            ->addStep(1, [
                'rules' => [
                    'username' => ['required', 'unique:users,username'],
                    'password' => ['required', Password::min(8)->numbers()]
                ],
            ])
            ->beforeStep(1, function(Form $form) {
                $username = $form->getValue('username');
                if($username !== '') {
                    $user = User::where('username', $username)->first();
                    if($user && $user->email_verified_at === null) {
                        $user->delete();
                    }
                }
            })
            ->addStep(2, [
                'rules' => [
                    'age' => ['required'],
                ],
            ])
            ->addStep(3, [
                'rules' => [
                    'email' => ['required', 'email']
                ],
            ])
            ->addStep(4, [
                'rules' => [
                    'code' => ['required', 'numeric', 'integer', 'digits:6']
                ],
            ])
            ->onStep(3, function (Form $form) {
                $user = User::create([
                    'username' => $form->getValue('username'),
                    'password' => bcrypt($form->getValue('password')),
                    'is_under_14' => $form->getValue('age') === "under",
                    'email' => $form->getValue('email'),
                ]);

                $form->setValue('user', $user->id);
                $code = $user->verificationCodes()->create(['type' => 'register']);
                dispatch((new SendEmailJob($user->email, new UserEmailVerify($user, $code->code))))->onQueue('mail');

            })
            ->onStep(4, function(Form $form) {
                $user = User::where('id', $form->getValue('user'))->first();
                if($user === null) {
                    return back()->with('message-type', 'danger')->with('message', 'The username was no longer found. Please try again.');
                }

                $verificationCode = $user->verificationCodes()->where('type', 'register')->where('code', $form->getValue('code'))->first();
                if($verificationCode === null) {
                    return back()->withErrors(['code' => 'The code is invalid']);
                }

                $user->email_verified_at = now();
                $user->save();
                $verificationCode->delete();

                auth()->login($user);
                request()->session()->regenerate();

                $form->reset();
                return redirect('/')->with('message', 'Your account is now registered and you have been logged in');
            });

        // user requested form reset
        if($request->has('reset')) {
            $form->reset();
            return redirect('/register');
        }

        // user requested email resend
        if(!empty($form->getValue('user')) && $request->has('resend')) {
            $user = User::where('id', $form->getValue('user'))->first();
            if($user === null) {
                return back()->with('message-type', 'danger')->with('message', 'The username was no longer found. Please try again.');
            }

            $code = $user->verificationCodes()
                ->where('type', 'register')
                ->orderByDesc('created_at')
                ->first();

            if ($code == null || now()->subSeconds(20)->greaterThan($code->created_at)) {
                $code = $user->verificationCodes()->create(['type' => 'register']);
                dispatch((new SendEmailJob($user->email, new UserEmailVerify($user, $code->code))))->onQueue('mail');
                return back()->with('message', 'Your verification code has been resent to your email address.');
            } else {
                return back()->with('message', 'Please wait at least 30 seconds before resending a verification code.');
            }
        }

        return $form;
    }

    public function store(Request $request) {
        /** @var \App\Models\User */
    }

    // Show Login Form
    public function login(Request $request) {
        if(auth()->check()) {
            return redirect('/');
        }

        return view('users.login');
    }

    // Authenticate User
    public function authenticate(Request $request) {
        $formFields = $request->validate([
            'username' => 'required',
            'password' => 'required'
        ]);

        if(auth()->validate($formFields)) {
            $user = User::where('username', $formFields['username'])->first();
            if($user->email_verified_at !== null) {
                auth()->login($user);
                $request->session()->regenerate();
                return redirect('/')->with('message', 'You are now logged in');
            }

            return redirect('/verify');
        }

        return back()->withErrors(['username' => 'Invalid username/password', 'password' => 'Invalid username/password'])->onlyInput('username');
    }

    // Logout User
    public function logout(Request $request) {
        auth()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('message', 'You have been logged out!');

    }

    public function verify(Request $request) {
        if(auth()->check()) {
            return redirect('/');
        }

        return view('users.verify');
    }
    
    public function verify_store(Request $request) {
        $request->validate([
            'code' => ['required', 'numeric', 'integer', 'digits:6'],
        ]);

        $verificationCode = VerificationCode::where('code', $request->code)->first();
        if($verificationCode === null) {
            return view('users.verify')->withErrors(['code' => 'The code is invalid'])->withInput($request->flash());
        }

        if($verificationCode->type === 'register') {
            $user = $verificationCode->user;
            $user->email_verified_at = now();
            $user->save();
            $verificationCode->delete();

            auth()->login($user);
            $request->session()->regenerate();

            return redirect('/')->with('message', 'Your account is now registered and you have been logged in');
        }

        abort(500);
    }
}
