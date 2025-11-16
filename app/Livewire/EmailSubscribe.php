<?php

namespace App\Livewire;

use App\Jobs\SendEmail;
use Carbon\Carbon;
use Livewire\Component;
use App\Models\EmailSubscriptions;
use App\Mail\UserWelcome;

class EmailSubscribe extends Component
{
    public string $email = '';
    public bool $success = false;
    public string $message = '';
    public string $trap = '';

    protected $rules = [
        'email' => 'required|email|max:255',
    ];

    public function subscribe(): void
    {
        // Honeypot - if this hidden field is filled, treat as success but do nothing
        if (! empty($this->trap)) {
            $this->reset(['email', 'trap']);
            $this->success = true;
            $this->message = 'Thanks, you have been subscribed to our newsletter.';
            return;
        }

        // Simple rate limiting per session
//        $attempts = session('subscribe_attempts', 0);
//        if ($attempts >= 5) {
//            $this->success = false;
//            $this->message = 'Too many attempts. Please try again in a little while.';
//            return;
//        }
//        session(['subscribe_attempts' => $attempts + 1]);


        $this->validate();

        // Look up existing subscription by email
        $subscription = EmailSubscriptions::where('email', $this->email)->first();

        // If already confirmed, do not create a new record or resend confirmation
        if ($subscription && $subscription->confirmed) {
            // Optionally you could set a different flag or message here
            $this->success = false;
            $this->message = 'That email is already subscribed to our newsletter.';
        } else {
            // If no subscription exists, create a new unconfirmed one
            if (!$subscription) {
                $subscription = EmailSubscriptions::create([
                    'email'     => $this->email,
                    'confirmed' => Carbon::now()
                ]);

                $subscription->save();
            }

            dispatch(new SendEmail($subscription->email, new UserWelcome($subscription->email)))->onQueue('mail');

            $this->success = true;
            $this->message = 'Thanks, you have been subscribed to our newsletter.';
        }

        $this->reset(['email', 'trap']);
    }

    public function render()
    {
        return view('livewire.email-subscribe');
    }
}
