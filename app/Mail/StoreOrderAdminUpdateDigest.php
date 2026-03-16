<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderAdminUpdateDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{
     *     order_number: string,
     *     status_label: string,
     *     admin_url: string,
     *     customer_name: string,
     *     customer_email: string,
     *     updates: array<int, array{time: ?string, summary: string, detail: ?string}>
     * }>  $orders
     */
    public function __construct(
        public string $digestDateLabel,
        public array $orders,
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject('Store order update digest for '.$this->digestDateLabel)
            ->markdown('emails.store-order-admin-update-digest');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
