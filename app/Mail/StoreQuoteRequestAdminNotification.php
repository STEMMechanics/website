<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreQuoteRequestAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public string $adminUrl,
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject('Store shipping quote requested for '.$this->quote->quote_number)
            ->markdown('emails.store-quote-request-admin-notification');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
