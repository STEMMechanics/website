<?php

namespace App\Jobs;

use App\Models\StoreOrder;
use App\Models\SentEmail;
use App\Services\StoreOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendDeferredStoreOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $storeOrderId,
        public readonly string $sentEmailId,
    )
    {
        $this->onQueue('mail');
    }

    public function handle(StoreOrderService $storeOrders): void
    {
        try {
            $order = StoreOrder::query()
                ->with(['invoice.allocations.customerPayment', 'items.downloads.media', 'items.product.hero', 'items.variant', 'coupon'])
                ->find($this->storeOrderId);

            if (! $order instanceof StoreOrder) {
                $this->markSentEmailFailed('Deferred store order email could not find order #'.$this->storeOrderId);

                return;
            }

            if ($order->isPaid() && $order->order_paid_emailed_at === null) {
                if ($storeOrders->sendOrderPaidEmailToCustomer($order)) {
                    $this->markSentEmailSent();
                } else {
                    $this->markSentEmailSkipped();
                }

                return;
            }

            if (! $order->isPaid() && $order->order_confirmation_emailed_at === null) {
                if ($storeOrders->sendOrderConfirmationEmailToCustomer($order)) {
                    $this->markSentEmailSent();
                } else {
                    $this->markSentEmailSkipped();
                }

                return;
            }

            $this->markSentEmailSkipped();
        } catch (Throwable $exception) {
            $this->markSentEmailFailed($exception->getMessage());

            throw $exception;
        }
    }

    private function markSentEmailSent(): void
    {
        SentEmail::query()
            ->whereKey($this->sentEmailId)
            ->where('status', SentEmail::STATUS_SCHEDULED)
            ->update([
                'status' => SentEmail::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);
    }

    private function markSentEmailSkipped(): void
    {
        SentEmail::query()
            ->whereKey($this->sentEmailId)
            ->where('status', SentEmail::STATUS_SCHEDULED)
            ->update([
                'status' => SentEmail::STATUS_SKIPPED,
                'sent_at' => null,
                'failed_at' => null,
                'error_message' => null,
            ]);
    }

    private function markSentEmailFailed(string $errorMessage): void
    {
        SentEmail::query()
            ->whereKey($this->sentEmailId)
            ->update([
                'status' => SentEmail::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => mb_substr($errorMessage, 0, 5000),
            ]);
    }
}
