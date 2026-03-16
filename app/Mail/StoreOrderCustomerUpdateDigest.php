<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderCustomerUpdateDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{
     *     order_number: string,
     *     status_label: string,
     *     order_url: string,
     *     updates: array<int, array{time: ?string, summary: string, detail: ?string}>
     * }>  $orders
     */
    public function __construct(
        public string $recipientName,
        public string $digestDateLabel,
        public array $orders,
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject('Your order updates for '.$this->digestDateLabel)
            ->markdown('emails.store-order-customer-update-digest');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
