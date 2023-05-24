<?php

namespace App\Http\Controllers\Api;

use App\Conductors\SubscriptionConductor;
use App\Enum\HttpResponseCodes;
use App\Models\Subscription;
use App\Http\Requests\SubscriptionRequest;
use App\Jobs\SendEmailJob;
use App\Mail\SubscriptionConfirm;
use App\Mail\SubscriptionUnsubscribed;
use Illuminate\Http\Request;

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
     * @param \Illuminate\Http\Request $request The endpoint request.
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        list($collection, $total) = SubscriptionConductor::request($request);

        return $this->respondAsResource(
            $collection,
            ['isCollection' => true,
                'appendData' => ['total' => $total]
            ]
        );
    }

    /**
     * Display the specified user.
     *
     * @param \Illuminate\Http\Request $request      The endpoint request.
     * @param  \App\Models\Subscription $subscription The subscription model.
     * @return \Illuminate\Http\Response
     */
    public function show(Request $request, Subscription $subscription)
    {
        if (SubscriptionConductor::viewable($subscription) === true) {
            return $this->respondAsResource(SubscriptionConductor::model($request, $subscription));
        }

        return $this->respondForbidden();
    }

    /**
     * Store a subscriber email in the database.
     *
     * @param  \App\Http\Requests\SubscriptionRequest $request The subscriber update request.
     * @return \Illuminate\Http\Response
     */
    public function store(SubscriptionRequest $request)
    {
        if (SubscriptionConductor::creatable() === true) {
            Subscription::create($request->all());
            dispatch((new SendEmailJob($request->email, new SubscriptionConfirm($request->email))))->onQueue('mail');

            return $this->respondCreated();
        } else {
            return $this->respondForbidden();
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\SubscriptionRequest $request      The subscription update request.
     * @param  \App\Models\Subscription               $subscription The specified subscription.
     * @return \Illuminate\Http\Response
     */
    public function update(SubscriptionRequest $request, Subscription $subscription)
    {
        // if (EventConductor::updatable($event) === true) {
        //     $event->update($request->all());
        //     return $this->respondAsResource(EventConductor::model($request, $event));
        // }

        // return $this->respondForbidden();


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
        if (SubscriptionConductor::destroyable($subscription) === true) {
            $subscription->delete();
            return $this->respondNoContent();
        } else {
            return $this->respondForbidden();
        }
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
