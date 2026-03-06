<?php

namespace App\Http\Controllers;

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\EmailSubscriptions;
use App\Models\SentEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Throwable;

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

        $subscriptionEmails = $subscriptions->getCollection()
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter()
            ->unique()
            ->values();

        $latestNewsletterByEmail = collect();
        if ($subscriptionEmails->isNotEmpty()) {
            $latestNewsletterByEmail = SentEmail::query()
                ->where('mailable_class', UpcomingWorkshops::class)
                ->whereIn('recipient', $subscriptionEmails->all())
                ->orderBy('created_at', 'desc')
                ->get()
                ->groupBy(fn (SentEmail $sentEmail) => strtolower(trim((string) $sentEmail->recipient)))
                ->map(fn (Collection $sentEmails) => $sentEmails->first());
        }

        return view('admin.subscription.index', [
            'subscriptions' => $subscriptions,
            'latestNewsletterByEmail' => $latestNewsletterByEmail,
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
            'confirmed' => now(),
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
            'confirmed' => now(),
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

    public function sendNow(EmailSubscriptions $subscription): RedirectResponse
    {
        $email = strtolower(trim((string) $subscription->email));

        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            session()->flash('message', 'Unable to send newsletter: subscription email is invalid.');
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        if ($subscription->confirmed === null || trim((string) $subscription->confirmed) === '') {
            session()->flash('message', 'Cannot send newsletter: this subscription is not confirmed.');
            session()->flash('message-title', 'Newsletter not sent');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        try {
            dispatch(new SendEmail($email, new UpcomingWorkshops($email)))->onQueue('mail');
        } catch (Throwable $exception) {
            session()->flash('message', 'Unable to queue newsletter: '.$exception->getMessage());
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Newsletter queued for '.$email.'.');
        session()->flash('message-title', 'Newsletter queued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function sendAllNow(): RedirectResponse
    {
        $emails = EmailSubscriptions::query()
            ->whereNotNull('confirmed')
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn (string $email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values();

        if ($emails->isEmpty()) {
            session()->flash('message', 'No confirmed subscriptions with valid email addresses were found.');
            session()->flash('message-title', 'Newsletter not sent');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        try {
            foreach ($emails as $email) {
                dispatch(new SendEmail($email, new UpcomingWorkshops($email)))->onQueue('mail');
            }
        } catch (Throwable $exception) {
            session()->flash('message', 'Unable to queue newsletters: '.$exception->getMessage());
            session()->flash('message-title', 'Newsletter failed');
            session()->flash('message-type', 'danger');

            return redirect()->back();
        }

        session()->flash('message', 'Newsletter queued for '.$emails->count().' confirmed subscriber'.($emails->count() === 1 ? '' : 's').'.');
        session()->flash('message-title', 'Newsletter queued');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }
}
