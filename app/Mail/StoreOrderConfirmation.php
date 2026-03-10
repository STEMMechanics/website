<?php

namespace App\Mail;

use App\Models\StoreOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public StoreOrder $order,
        public string $orderUrl,
    ) {}

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));
        $subject = $this->order->isPaid()
            ? 'Your order '.$this->order->order_number.' is ready'
            : 'Your order '.$this->order->order_number.' has been created';

        $mail = $this
            ->subject($subject)
            ->markdown('emails.store-order-confirmation');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        return $mail;
    }
}
