<?php

namespace App\Mail;

use App\Models\SiteOption;
use App\Models\StoreOrderUpdate;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderCustomerUpdateNotice extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  array<int, array{
     *     order_number: string,
     *     status_label: string,
     *     notification_type?: string,
     *     order_url: string,
     *     item_sections?: array<int, array{
     *         heading: string,
     *         items: array<int, array{title: string, quantity: int, detail: ?string}>
     *     }>,
     *     updates: array<int, array{type?: string, time: ?string, summary: string, detail: ?string}>
     * }>  $orders
     */
    public function __construct(
        public string $recipientName,
        public array $orders,
    ) {
        $primaryOrder = count($this->orders) === 1 ? ($this->orders[0] ?? null) : null;
        $notificationType = (string) ($primaryOrder['notification_type'] ?? 'updated');

        $this->subjectLine = is_array($primaryOrder)
            ? $this->customerSubject((string) ($primaryOrder['order_number'] ?? ''), $notificationType)
            : 'Your orders have been updated';
        $this->introLine = is_array($primaryOrder)
            ? $this->customerIntro((string) ($primaryOrder['order_number'] ?? ''), $notificationType)
            : 'Your orders have been updated.';
        $this->detailLine = is_array($primaryOrder)
            ? $this->customerDetailLine($notificationType)
            : null;
        $this->showOrderBreakdown = is_array($primaryOrder)
            ? ! $this->isSingleOrderStatusOnlyNotice($primaryOrder, $notificationType)
            : true;
    }

    public string $subjectLine;

    public string $introLine;

    public ?string $detailLine;

    public bool $showOrderBreakdown;

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject($this->subjectLine)
            ->markdown('emails.store-order-customer-update-notice')
            ->with([
                'recipientName' => $this->recipientName,
                'orders' => $this->orders,
                'introLine' => $this->introLine,
                'detailLine' => $this->detailLine,
                'showOrderBreakdown' => $this->showOrderBreakdown,
            ]);

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }

    private function customerSubject(string $orderNumber, string $notificationType): string
    {
        $orderNumber = trim($orderNumber);

        return match ($notificationType) {
            'shipped' => 'Your order '.$orderNumber.' has now shipped',
            'partially_shipped' => 'Part of your order '.$orderNumber.' has now shipped',
            'items_cancelled' => 'Some items on your order '.$orderNumber.' were cancelled',
            'cancelled' => 'Your order '.$orderNumber.' has been cancelled',
            'ready_for_pickup' => 'Your order '.$orderNumber.' is now ready for pickup',
            'collected' => 'Your order '.$orderNumber.' has been collected',
            'fulfilled' => 'Your order '.$orderNumber.' is now complete',
            'preparing' => 'Your order '.$orderNumber.' is now being prepared',
            default => 'Your order '.$orderNumber.' has been updated',
        };
    }

    private function customerIntro(string $orderNumber, string $notificationType): string
    {
        $orderNumber = trim($orderNumber);

        return match ($notificationType) {
            'shipped' => 'Your order, '.$orderNumber.', has now shipped.',
            'partially_shipped' => 'Part of your order, '.$orderNumber.', has now shipped.',
            'items_cancelled' => 'Some items on your order, '.$orderNumber.', were cancelled.',
            'cancelled' => 'Your order, '.$orderNumber.', has been cancelled.',
            'ready_for_pickup' => 'Your order, '.$orderNumber.', is now ready for pickup.',
            'collected' => 'Your order, '.$orderNumber.', has been collected.',
            'fulfilled' => 'Your order, '.$orderNumber.', is now complete.',
            'preparing' => 'Your order, '.$orderNumber.', is now being prepared.',
            default => 'Your order has been updated.',
        };
    }

    private function customerDetailLine(string $notificationType): ?string
    {
        if ($notificationType !== 'ready_for_pickup') {
            return null;
        }

        $default = SiteOption::defaultValue('store.order.ready-for-pickup-message') ?? '';
        $message = trim((string) SiteOption::value('store.order.ready-for-pickup-message', $default));

        return $message !== '' ? $message : null;
    }

    /**
     * @param  array{
     *     updates?: array<int, array{type?: string}>
     * }  $primaryOrder
     */
    private function isSingleOrderStatusOnlyNotice(array $primaryOrder, string $notificationType): bool
    {
        if ($notificationType === 'updated') {
            return false;
        }

        $updates = collect((array) ($primaryOrder['updates'] ?? []));
        if ($updates->isEmpty()) {
            return true;
        }

        return $updates->every(
            fn (array $update): bool => (string) ($update['type'] ?? '') === StoreOrderUpdate::EVENT_STATUS_CHANGED
        );
    }
}
