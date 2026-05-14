<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderAdminUpdateNotice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{
     *     order_number: string,
     *     status_label: string,
     *     notification_type?: string,
     *     admin_url: string,
     *     customer_name: string,
     *     customer_email: string,
     *     updates: array<int, array{time: ?string, summary: string, detail: ?string}>
     * }>  $orders
     */
    public function __construct(
        public array $orders,
    ) {
        $primaryOrder = count($this->orders) === 1 ? ($this->orders[0] ?? null) : null;
        $notificationType = (string) ($primaryOrder['notification_type'] ?? 'updated');

        $this->subjectLine = is_array($primaryOrder)
            ? $this->adminSubject((string) ($primaryOrder['order_number'] ?? ''), $notificationType)
            : 'Immediate update for store orders';
        $this->headline = is_array($primaryOrder)
            ? $this->adminHeadline($notificationType)
            : 'Store Order Updated';
        $this->introLine = is_array($primaryOrder)
            ? $this->adminIntro($notificationType)
            : 'A store order has been updated.';
    }

    public string $subjectLine;

    public string $headline;

    public string $introLine;

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject($this->subjectLine)
            ->markdown('emails.store-order-admin-update-notice')
            ->with([
                'orders' => $this->orders,
                'headline' => $this->headline,
                'introLine' => $this->introLine,
            ]);

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }

    private function adminSubject(string $orderNumber, string $notificationType): string
    {
        $orderNumber = trim($orderNumber);

        return match ($notificationType) {
            'shipped' => 'Store order '.$orderNumber.' shipped',
            'partially_shipped' => 'Store order '.$orderNumber.' partially shipped',
            'partially_collected' => 'Store order '.$orderNumber.' partially collected',
            'ready_for_partial_collection' => 'Store order '.$orderNumber.' ready for partial collection',
            'items_cancelled' => 'Items on store order '.$orderNumber.' cancelled',
            'cancelled' => 'Store order '.$orderNumber.' cancelled',
            'ready_for_pickup' => 'Store order '.$orderNumber.' ready for pickup',
            'collected' => 'Store order '.$orderNumber.' collected',
            'fulfilled' => 'Store order '.$orderNumber.' complete',
            'preparing' => 'Store order '.$orderNumber.' preparing',
            default => 'Immediate update for order '.$orderNumber,
        };
    }

    private function adminHeadline(string $notificationType): string
    {
        return match ($notificationType) {
            'shipped' => 'Store Order Shipped',
            'partially_shipped' => 'Store Order Partially Shipped',
            'partially_collected' => 'Store Order Partially Collected',
            'ready_for_partial_collection' => 'Store Order Ready for Partial Collection',
            'items_cancelled' => 'Store Order Items Cancelled',
            'cancelled' => 'Store Order Cancelled',
            'ready_for_pickup' => 'Store Order Ready for Pickup',
            'collected' => 'Store Order Collected',
            'fulfilled' => 'Store Order Complete',
            'preparing' => 'Store Order Preparing',
            default => 'Store Order Updated',
        };
    }

    private function adminIntro(string $notificationType): string
    {
        return match ($notificationType) {
            'shipped' => 'A store order has now shipped.',
            'partially_shipped' => 'Part of a store order has now shipped.',
            'partially_collected' => 'Part of a store order has now been collected.',
            'ready_for_partial_collection' => 'Some items on a store order are now ready for partial collection.',
            'items_cancelled' => 'Some items on a store order were cancelled.',
            'cancelled' => 'A store order has now been cancelled.',
            'ready_for_pickup' => 'A store order is now ready for pickup.',
            'collected' => 'A store order has now been collected.',
            'fulfilled' => 'A store order is now complete.',
            'preparing' => 'A store order is now being prepared.',
            default => 'A store order has been updated.',
        };
    }
}
