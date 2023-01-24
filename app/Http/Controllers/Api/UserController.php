<?php

namespace App\Http\Controllers\Api;

use App\Enum\HttpResponseCodes;
use App\Filters\UserFilter;
use App\Http\Requests\UserUpdateRequest;
use App\Http\Requests\UserStoreRequest;
use App\Http\Requests\UserForgotPasswordRequest;
use App\Http\Requests\UserForgotUsernameRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Requests\UserResendVerifyEmailRequest;
use App\Http\Requests\UserResetPasswordRequest;
use App\Http\Requests\UserVerifyEmailRequest;
use App\Jobs\SendEmailJob;
use App\Mail\ChangedEmail;
use App\Mail\ChangedPassword;
use App\Mail\ChangeEmailVerify;
use App\Mail\ForgotUsername;
use App\Mail\ForgotPassword;
use App\Mail\EmailVerify;
use App\Models\User;
use App\Models\UserCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->except([
            'index',
            'show',
            'register',
            'exists',
            'forgotPassword',
            'forgotUsername',
            'resetPassword',
            'verifyEmail',
            'resendVerifyEmailCode'
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param \App\Filters\UserFilter $filter Filter object.
     * @return \Illuminate\Http\Response
     */
    public function index(UserFilter $filter)
    {
        $collection = $filter->filter();
        return $this->respondAsResource(
            $collection,
            ['total' => $filter->foundTotal()]
        );
    }

    /**
     * Store a newly created user in the database.
     *
     * @param  UserStoreRequest $request The user update request.
     * @return \Illuminate\Http\Response
     */
    public function store(UserStoreRequest $request)
    {
        if ($request->user()->hasPermission('admin/user') !== true) {
            return $this->respondForbidden();
        }

        $user = User::create($request->all());
        return $this->respondAsResource((new UserFilter($request))->filter($user), [], HttpResponseCodes::HTTP_CREATED);
    }


    /**
     * Display the specified user.
     *
     * @param  UserFilter $filter The user filter.
     * @param  User       $user   The user model.
     * @return \Illuminate\Http\Response
     */
    public function show(UserFilter $filter, User $user)
    {
        return $this->respondAsResource($filter->filter($user));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  UserUpdateRequest $request The user update request.
     * @param  User              $user    The specified user.
     * @return \Illuminate\Http\Response
     */
    public function update(UserUpdateRequest $request, User $user)
    {
        $input = [];
        $updatable = ['username', 'first_name', 'last_name', 'email', 'phone', 'password'];

        if ($request->user()->hasPermission('admin/user') === true) {
            $updatable = array_merge($updatable, ['email_verified_at']);
        } elseif ($request->user()->is($user) !== true) {
            return $this->respondForbidden();
        }

        $input = $request->only($updatable);
        if (array_key_exists('password', $input) === true) {
            $input['password'] = Hash::make($request->input('password'));
        }

        $user->update($input);

        return $this->respondAsResource((new UserFilter($request))->filter($user));
    }


    /**
     * Remove the user from the database.
     *
     * @param  User $user The specified user.
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        if ($user->hasPermission('admin/user') === false) {
            return $this->respondForbidden();
        }

        $user->delete();
        return $this->respondNoContent();
    }

    /**
     * Register a new user
     *
     * @param UserRegisterRequest $request The register user request.
     * @return \Illuminate\Http\Response
     */
    public function register(UserRegisterRequest $request)
    {
        try {
            $user = User::create([
                'first_name' => $request->input('first_name'),
                'last_name' => $request->input('last_name'),
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'phone' => $request->input('phone'),
                'password' => Hash::make($request->input('password'))
            ]);

            $code = $user->codes()->create([
                'action' => 'verify-email',
            ]);

            dispatch((new SendEmailJob($user->email, new EmailVerify($user, $code->code))))->onQueue('mail');

            return response()->json([
                'message' => 'Check your email for a welcome code.'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'A server error occurred. Please try again later' . $e
            ], 500);
        }//end try
    }

    /**
     * Sends an email with all the usernames registered at that address
     *
     * @param UserForgotUsernameRequest $request The forgot username request.
     * @return \Illuminate\Http\Response
     */
    public function forgotUsername(UserForgotUsernameRequest $request)
    {
        $users = User::where('email', $request->input('email'))->whereNotNull('email_verified_at')->get();
        if ($users->count() > 0) {
            dispatch((new SendEmailJob(
                $users->first()->email,
                new ForgotUsername($users->pluck('username')->toArray())
            )))->onQueue('mail');
            return $this->respondNoContent();
        }

        return $this->respondJson(['message' => 'Username send to the email address if registered']);
    }

    /**
     * Generates a new reset password code
     *
     * @param UserForgotPasswordRequest $request The reset password request.
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(UserForgotPasswordRequest $request)
    {
        $user = User::where('username', $request->input('username'))->first();
        if ($user !== null) {
            $user->codes()->where('action', 'reset-password')->delete();
            $code = $user->codes()->create([
                'action' => 'reset-password'
            ]);

            dispatch((new SendEmailJob($user->email, new ForgotPassword($user, $code->code))))->onQueue('mail');
            return $this->respondNoContent();
        }

        return $this->respondNotFound();
    }

    /**
     * Resets a user password
     *
     * @param UserResetPasswordRequest $request The reset password request.
     * @return \Illuminate\Http\Response
     */
    public function resetPassword(UserResetPasswordRequest $request)
    {
        UserCode::clearExpired();

        $code = UserCode::where('code', $request->input('code'))->where('action', 'reset-password')->first();
        if ($code !== null) {
            $user = $code->user()->first();

            $code->delete();
            $user->codes()->where('action', 'verify-email')->delete();

            $user->password = Hash::make($request->input('password'));

            if ($user->email_verified_at === null) {
                $user->email_verified_at = now();
            }

            $user->save();

            dispatch((new SendEmailJob($user->email, new ChangedPassword($user))))->onQueue('mail');
            return $this->respondNoContent();
        }

        return $this->respondError([
            'code' => 'The code was not found or has expired'
        ]);
    }

    /**
     * Verify an email code
     *
     * @param UserVerifyEmailRequest $request The verify email request.
     * @return \Illuminate\Http\Response
     */
    public function verifyEmail(UserVerifyEmailRequest $request)
    {
        UserCode::clearExpired();

        $code = UserCode::where('code', $request->input('code'))->where('action', 'verify-email')->first();
        if ($code !== null) {
            $user = $code->user()->first();
            $new_email = $code->data;

            if ($new_email === null) {
                if ($user->email_verified_at === null) {
                    $user->email_verified_at = now();
                }
            } else {
                dispatch((new SendEmailJob($user->email, new ChangedEmail($user, $user->email, $new_email))))
                    ->onQueue('mail');

                $user->email = $new_email;
                $user->email_verified_at = now();
            }

            $code->delete();
            $user->save();

            return $this->respondNoContent();
        }//end if

        return $this->respondWithErrors([
            'code' => 'The code was not found or has expired'
        ]);
    }

    /**
     * Resend a new verify email
     *
     * @param UserResendVerifyEmailRequest $request The resend verify email request.
     * @return \Illuminate\Http\Response
     */
    public function resendVerifyEmail(UserResendVerifyEmailRequest $request)
    {
        UserCode::clearExpired();

        $user = User::where('username', $request->input('username'))->first();
        if ($user !== null) {
            $code = $user->codes()->where('action', 'verify-email')->first();
            $code->regenerate();
            $code->save();

            if ($code->data === null) {
                dispatch((new SendEmailJob($user->email, new EmailVerify($user, $code->code))))->onQueue('mail');
            } else {
                dispatch((new SendEmailJob($user->email, new ChangeEmailVerify($user, $code->code, $code->data))))
                    ->onQueue('mail');
            }
        }

        return response()->json(['message' => 'Verify email sent if user registered and required']);
    }

    /**
     * Resend verification email
     *
     * @param UserResendVerifyEmailRequest $request The resend user request.
     * @return \Illuminate\Http\Response
     */
    public function resendVerifyEmailCode(UserResendVerifyEmailRequest $request)
    {
        $user = User::where('username', $request->input('username'))->first();
        if ($user !== null) {
            $user->codes()->where('action', 'verify-email')->delete();

            if ($user->email_verified_at === null) {
                $code = $user->codes()->create([
                    'action' => 'verify-email'
                ]);

                dispatch((new SendEmailJob($user->email, new EmailVerify($user, $code->code))))->onQueue('mail');
            }

            return $this->respondNoContent();
        }

        return $this->respondNotFound();
    }
}
