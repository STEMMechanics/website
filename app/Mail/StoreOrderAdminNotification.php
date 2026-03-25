<?php

namespace App\Mail;

use App\Models\StoreOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public StoreOrder $order,
        public string $adminUrl,
        public string $notificationType = 'created',
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));
        $subject = match ($this->notificationType) {
            'paid' => 'Store order '.$this->order->order_number.' paid',
            'manual_quote_requested' => 'Shipping quote requested for store order '.$this->order->order_number,
            default => 'New store order '.$this->order->order_number,
        };

        $mail = $this
            ->subject($subject)
            ->markdown('emails.store-order-admin-notification');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
