<?php

namespace App\Http\Controllers\Api;

use App\Conductors\EventConductor;
use App\Enum\HttpResponseCodes;
use App\Http\Requests\UserRequest;
use App\Http\Requests\UserForgotPasswordRequest;
use App\Http\Requests\UserRegisterRequest;
use App\Http\Requests\UserResendVerifyEmailRequest;
use App\Http\Requests\UserResetPasswordRequest;
use App\Http\Requests\UserVerifyEmailRequest;
use App\Jobs\SendEmailJob;
use App\Mail\ChangedEmail;
use App\Mail\ChangedPassword;
use App\Mail\ChangeEmailVerify;
use App\Mail\ForgotPassword;
use App\Mail\EmailVerify;
use App\Models\User;
use App\Models\UserCode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Conductors\UserConductor;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Container\BindingResolutionException;

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
            'resetPassword',
            'verifyEmail',
            'resendVerifyEmailCode',
            'eventList',
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = UserConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['isCollection' => true,
                'appendData' => ['total' => $total]
            ]
        );
    }

    /**
     * Store a newly created user in the database.
     *
     * @param \App\Http\Requests\UserRequest $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function store(UserRequest $request)
    {
        if (UserConductor::creatable() === true) {
            $user = User::create($request->all());
            return $this->respondAsResource(
                UserConductor::model($request, $user),
                ['respondCode' => HttpResponseCodes::HTTP_CREATED]
            );
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Display the specified user.
     *
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @param  \App\Models\User         $user    The user model.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, User $user)
    {
        if (UserConductor::viewable($user) === true) {
            return $this->respondAsResource(UserConductor::model($request, $user));
        }

        return $this->respondForbidden();
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UserRequest $request The user update request.
     * @param  \App\Models\User               $user    The specified user.
     * @return \Illuminate\Http\Response
     */
    public function update(UserRequest $request, User $user)
    {
        if (UserConductor::updatable($user) === true) {
            $input = [];
            $updatable = ['first_name', 'last_name', 'email', 'phone', 'password', 'display_name'];

            if ($request->user()->hasPermission('admin/user') === true) {
                $updatable = array_merge($updatable, ['email_verified_at']);
            }

            $input = $request->only($updatable);
            if (array_key_exists('password', $input) === true) {
                $input['password'] = Hash::make($request->input('password'));
            }

            $user->update($input);

            return $this->respondAsResource(UserConductor::model($request, $user));
        }

        return $this->respondForbidden();
    }

    /**
     * Remove the user from the database.
     *
     * @param  \App\Models\User $user The specified user.
     * @return \Illuminate\Http\Response
     */
    public function destroy(User $user)
    {
        if (UserConductor::destroyable($user) === true) {
            $user->delete();
            return $this->respondNoContent();
        }

        return $this->respondForbidden();
    }

    /**
     * Register a new user
     *
     * @param \App\Http\Requests\UserRegisterRequest $request The register user request.
     * @return JsonResponse
     */
    public function register(UserRegisterRequest $request): JsonResponse
    {
        try {
            $userData = $request->only([
                'first_name',
                'last_name',
                'email',
                'phone',
                'password',
                'display_name',
            ]);

            $userData['password'] = Hash::make($userData['password']);

            $user = User::where('email', $request->input('email'))
                ->whereNull('password')
                ->first();

            if ($user === null) {
                $user = User::create($userData);
            } else {
                unset($userData['email']);
                $user->update($userData);
            }//end if

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
     * Generates a new reset password code
     *
     * @param \App\Http\Requests\UserForgotPasswordRequest $request The reset password request.
     * @return \Illuminate\Http\Response
     */
    public function forgotPassword(UserForgotPasswordRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();
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
     * @param \App\Http\Requests\UserResetPasswordRequest $request The reset password request.
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
            'code' => 'The code was not found or has expired.'
        ]);
    }

    /**
     * Verify an email code
     *
     * @param \App\Http\Requests\UserVerifyEmailRequest $request The verify email request.
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
            'code' => 'The code was not found or has expired.'
        ]);
    }

    /**
     * Resend a new verify email
     *
     * @param \App\Http\Requests\UserResendVerifyEmailRequest $request The resend verify email request.
     * @return JsonResponse
     */
    public function resendVerifyEmail(UserResendVerifyEmailRequest $request): JsonResponse
    {
        UserCode::clearExpired();

        $user = User::where('email', $request->input('email'))->first();
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
     * @param \App\Http\Requests\UserResendVerifyEmailRequest $request The resend user request.
     * @return \Illuminate\Http\Response
     */
    public function resendVerifyEmailCode(UserResendVerifyEmailRequest $request)
    {
        $user = User::where('email', $request->input('email'))->first();
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

    /**
     * Return a JSON event list of a user.
     *
     * @param Request $request The http request.
     * @param User    $user    The specified user.
     * @return JsonResponse
     */
    public function eventList(Request $request, User $user): JsonResponse
    {
        if (
            $request->user() !== null && (
            $request->user() === $user || $request->user()->hasPermission('admin/events') === true
            )
        ) {
            $collection = $user->events;
            $total = $collection->count();

            $collection = EventConductor::collection($request, $collection);
            return $this->respondAsResource(
                $collection,
                ['isCollection' => true,
                    'appendData' => ['total' => $total]
                ]
            );
        } else {
            return $this->respondForbidden();
        }
    }
}
