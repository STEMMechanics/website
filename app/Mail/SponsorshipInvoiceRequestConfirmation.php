<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorshipInvoiceRequestConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $confirmUrl,
        public string $email,
        public string $organisationName,
        public string $amount,
        public string $frequency = 'one_time',
    ) {}

    public function build(): static
    {
        return $this->subject('Confirm your STEMMechanics sponsorship')
            ->markdown('emails.sponsorship-invoice-request-confirmation');
    }
}
