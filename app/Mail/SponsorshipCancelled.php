<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorshipCancelled extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $amount,
        public string $cancelledAt,
        public string $manageUrl,
        public string $email,
        public string $billingMethod = 'square',
    ) {}

    public function build(): static
    {
        return $this
            ->subject('Your monthly sponsorship has been cancelled')
            ->markdown('emails.sponsorship-cancelled');
    }
}
