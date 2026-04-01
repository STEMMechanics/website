<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PendingBankTransferPaymentsDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{
     *     id: int,
     *     customer_name: string,
     *     customer_email: string,
     *     received_on: string,
     *     age_label: string,
     *     amount: string,
     *     reference: ?string,
     *     notes: ?string,
     *     edit_url: string,
     *     allocations: array<int, string>
     * }>  $payments
     */
    public function __construct(
        public string $digestDateLabel,
        public array $payments,
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.payment_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.payment_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject('Pending bank transfers awaiting clearance: '.count($this->payments))
            ->markdown('emails.pending-bank-transfer-payments-digest');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
