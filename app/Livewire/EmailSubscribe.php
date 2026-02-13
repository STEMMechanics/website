<?php

namespace App\Livewire;

use App\Jobs\SendEmail;
use Carbon\Carbon;
use Livewire\Component;
use App\Models\EmailSubscriptions;
use App\Mail\UserWelcome;

class EmailSubscribe extends Component
{
    // Client-controlled
    public string $email = '';
    public string $trap = '';

    // Server-controlled
    protected bool $successState = false;
    protected string $messageText = '';
    protected int $renderedAt = 0; // unix timestamp

    protected $rules = [
        'email' => 'required|email|max:255',
    ];

    /**
     * Expose server-controlled state to the view without allowing client updates.
     */
    public function getSuccessProperty(): bool
    {
        return $this->successState;
    }

    public function getMessageProperty(): string
    {
        return $this->messageText;
    }

    /**
     * Only allow the client to update user-input fields.
     */
    public function updating($name, $value)
    {
        if (! in_array($name, ['email', 'trap'], true)) {
            return false;
        }
    }

    public function mount(): void
    {
        // Mount only runs on the initial page load.
        $this->renderedAt = now()->timestamp;
    }

    public function hydrate(): void
    {
        // Protected properties are not hydrated from the snapshot on subsequent requests,
        // so ensure it is initialized for action calls like subscribe().
        if ($this->renderedAt === 0) {
            $this->renderedAt = now()->timestamp;
        }
    }

    public function subscribe(): void
    {
        $this->validate();

        // 1. Honeypot - if this hidden field is filled, treat as success but do nothing
        if (! empty($this->trap)) {
            $this->reset(['email', 'trap']);
            $this->successState = true;
            $this->messageText = 'Thanks, you have been subscribed to our newsletter.';
            return;
        }

        // 2. Block submits in first few seconds after render
        if (now()->timestamp - $this->renderedAt < 4) {
            $this->successState = false;
            $this->messageText = 'That was a bit quick. Please wait a few seconds and try again.';
            return;
        }

        // 3. Enforce 30 seconds between attempts per session
        $lastAttempt = session('subscribe_last_attempt'); // int timestamp or null
        if (! is_int($lastAttempt)) {
            $lastAttempt = null;
        }

        $now = time();

        if ($lastAttempt && ($now - $lastAttempt) < 20) {
            $this->successState = false;
            $this->messageText = 'Please wait a little before trying again.';
            return;
        }

        session(['subscribe_last_attempt' => $now]);

        // 4. Limit to 5 attempts per session (your existing logic)
        $attempts = session('subscribe_attempts', 0);
        if ($attempts >= 5) {
            $this->successState = false;
            $this->messageText = 'Too many attempts. Please try again in a little while.';
            return;
        }
        session(['subscribe_attempts' => $attempts + 1]);

        // Look up existing subscription by email
        $subscription = EmailSubscriptions::where('email', $this->email)->first();

        // If already confirmed, do not create a new record or resend confirmation
        if ($subscription && $subscription->confirmed) {
            // Optionally you could set a different flag or message here
            $this->successState = false;
            $this->messageText = 'That email is already subscribed to our newsletter.';
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

            $this->successState = true;
            $this->messageText = 'Thanks, you have been subscribed to our newsletter.';
        }

        $this->reset(['email', 'trap']);
    }

    public function render()
    {
        return view('livewire.email-subscribe');
    }
}
