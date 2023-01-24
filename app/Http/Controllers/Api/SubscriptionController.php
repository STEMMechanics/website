<?php

namespace App\Http\Controllers\Api;

use App\Models\Subscription;
use App\Filters\SubscriptionFilter;
use App\Http\Requests\SubscriptionRequest;
use App\Jobs\SendEmailJob;
use App\Mail\SubscriptionConfirm;
use App\Mail\SubscriptionUnsubscribed;

class SubscriptionController extends ApiController
{
    /**
     * ApplicationController constructor.
     */
    public function __construct()
    {
        $this->middleware('auth:sanctum')
        ->except(['store', 'destroyByEmail']);
    }

    /**
     * Display a listing of subscribers.
     *
     * @param \App\Filters\SubscriptionFilter $filter Filter object.
     * @return \Illuminate\Http\Response
     */
    public function index(SubscriptionFilter $filter)
    {
        $collection = $filter->filter();
        return $this->respondAsResource(
            $collection,
            ['total' => $filter->foundTotal()]
        );
    }

    /**
     * Store a subscriber email in the database.
     *
     * @param  SubscriptionRequest $request The subscriber update request.
     * @return \Illuminate\Http\Response
     */
    public function store(SubscriptionRequest $request)
    {
        if (Subscription::where('email', $request->email)->first() !== null) {
            return $this->respondWithErrors(['email' => 'This email address has already subscribed']);
        }

        Subscription::create($request->all());
        dispatch((new SendEmailJob($request->email, new SubscriptionConfirm($request->email))))->onQueue('mail');

        return $this->respondCreated();
    }


    /**
     * Display the specified user.
     *
     * @param  SubscriptionFilter $filter       The subscription filter.
     * @param  Subscription       $subscription The subscription model.
     * @return \Illuminate\Http\Response
     */
    public function show(SubscriptionFilter $filter, Subscription $subscription)
    {
        return $this->respondAsResource($filter->filter($subscription));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  SubscriptionRequest $request      The subscription update request.
     * @param  Subscription        $subscription The specified subscription.
     * @return \Illuminate\Http\Response
     */
    public function update(SubscriptionRequest $request, Subscription $subscription)
    {
        // $input = [];
        // $updatable = ['username', 'first_name', 'last_name', 'email', 'phone', 'password'];

        // if ($request->user()->hasPermission('admin/user') === true) {
        //     $updatable = array_merge($updatable, ['email_verified_at']);
        // } elseif ($request->user()->is($user) !== true) {
        //     return $this->respondForbidden();
        // }

        // $input = $request->only($updatable);
        // if (array_key_exists('password', $input) === true) {
        //     $input['password'] = Hash::make($request->input('password'));
        // }

        // $user->update($input);

        // return $this->respondAsResource((new UserFilter($request))->filter($user));
    }


    /**
     * Remove the user from the database.
     *
     * @param  Subscription $subscription The specified subscription.
     * @return \Illuminate\Http\Response
     */
    public function destroy(Subscription $subscription)
    {
        // if ($user->hasPermission('admin/user') === false) {
        //     return $this->respondForbidden();
        // }

        $email = $subscription->email;

        $subscription->delete();
        return $this->respondNoContent();
    }

    /**
     * Remove the user from the database.
     *
     * @param  SubscriptionRequest $request The specified subscription.
     * @return \Illuminate\Http\Response
     */
    public function destroyByEmail(SubscriptionRequest $request)
    {
        $subscription = Subscription::where('email', $request->email)->first();
        if ($subscription !== null) {
            $subscription->delete();
            dispatch((new SendEmailJob($request->email, new SubscriptionUnsubscribed($request->email))))->onQueue('mail');
        }

        return $this->respondNoContent();
    }
}
