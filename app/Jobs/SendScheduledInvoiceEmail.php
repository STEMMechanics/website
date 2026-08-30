<?php

namespace App\Jobs;

use App\Mail\ScheduledInvoiceFailure;
use App\Models\Invoice;
use App\Services\AdminRecipientService;
use App\Services\ScheduledInvoiceDeliveryService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendScheduledInvoiceEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(public int $invoiceId)
    {
        $this->onQueue('mail');
    }

    public function handle(ScheduledInvoiceDeliveryService $delivery): void
    {
        $invoice = Invoice::query()->findOrFail($this->invoiceId);
        $delivery->deliverCustomerEmail($invoice);
        $invoice->update([
            'status' => Invoice::STATUS_SENT,
            'scheduled_email_sent_at' => now(),
            'scheduled_email_failed_at' => null,
            'scheduled_email_failure' => null,
        ]);
    }

    public function failed(Throwable $exception): void
    {
        Invoice::query()->whereKey($this->invoiceId)->update([
            'status' => Invoice::STATUS_DRAFT,
            'scheduled_email_queued_at' => null,
            'scheduled_email_failed_at' => now(),
            'scheduled_email_failure' => mb_substr($exception->getMessage(), 0, 5000),
        ]);
        $invoice = Invoice::query()->find($this->invoiceId);
        if ($invoice instanceof Invoice) {
            foreach (app(AdminRecipientService::class)->emails() as $email) {
                dispatch(new SendEmail($email, new ScheduledInvoiceFailure($invoice, $exception->getMessage())))->onQueue('mail');
            }
        }
    }
}
