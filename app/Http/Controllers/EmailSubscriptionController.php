<?php

namespace App\Http\Controllers;

use App\Models\EmailSubscriptions;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EmailSubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = EmailSubscriptions::query();

        if ($request->has('search') && $request->search !== '') {
            $query->where('email', 'like', '%' . $request->search . '%');
        }

        $subscriptions = $query
            ->orderBy('created_at', 'desc')
            ->paginate(20)
            ->onEachSide(1);

        return view('admin.subscription.index', [
            'subscriptions' => $subscriptions,
        ]);
    }

    public function create()
    {
        return view('admin.subscription.edit');
    }

    public function store(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email', 'max:255', 'unique:email_subscriptions,email'],
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        EmailSubscriptions::create([
            'email' => strtolower(trim($request->email)),
            'confirmed' => ($request->get('confirmed', false) === 'on') ? now() : null,
        ]);

        session()->flash('message', 'Subscription has been created');
        session()->flash('message-title', 'Subscription created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.subscription.index');
    }

    public function edit(EmailSubscriptions $subscription)
    {
        return view('admin.subscription.edit', compact('subscription'));
    }

    public function update(Request $request, EmailSubscriptions $subscription)
    {
        $request->validate([
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('email_subscriptions', 'email')->ignore($subscription->id),
            ],
        ], [
            'email.required' => __('validation.custom_messages.email_required'),
            'email.email' => __('validation.custom_messages.email_invalid'),
        ]);

        $subscription->update([
            'email' => strtolower(trim($request->email)),
            'confirmed' => ($request->get('confirmed', false) === 'on') ? ($subscription->confirmed ?: now()) : null,
        ]);

        session()->flash('message', 'Subscription has been updated');
        session()->flash('message-title', 'Subscription updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.subscription.index');
    }

    public function destroy(EmailSubscriptions $subscription)
    {
        $subscription->delete();

        session()->flash('message', 'Subscription has been deleted');
        session()->flash('message-title', 'Subscription deleted');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.subscription.index');
    }
}
