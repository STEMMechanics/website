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

class SubscriptionController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->except([]);
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
}
