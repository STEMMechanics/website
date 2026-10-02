<?php

namespace App\Console\Commands;

use App\Exceptions\UnbalancedInvoiceAllocation;
use App\Jobs\SendEmail;
use App\Jobs\SendScheduledInvoiceEmail;
use App\Mail\ScheduledInvoiceReview;
use App\Models\Invoice;
use App\Services\Finance\WorkshopAllocation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessScheduledInvoicesCommand extends Command
{
    protected $signature = 'invoices:process-scheduled';

    protected $description = 'Send review notices and issue scheduled invoices';

    public function handle(): int
    {
        $reviewed = 0;
        Invoice::query()->where('scheduled_email', true)->where('status', Invoice::STATUS_DRAFT)
            ->whereDate('issue_date', today()->addDay())->whereNull('scheduled_review_sent_at')->with('creator')
            ->each(function (Invoice $invoice) use (&$reviewed): void {
                $creator = $invoice->creator;
                $email = $creator?->isAdmin() ? trim((string) $creator->email) : '';
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $email = trim((string) config('mail.invoice_review_fallback'));
                }
                $recipients = filter_var($email, FILTER_VALIDATE_EMAIL) ? [$email] : [];
                foreach ($recipients as $email) {
                    dispatch(new SendEmail($email, new ScheduledInvoiceReview($invoice)))->onQueue('mail');
                }
                if ($recipients !== []) {
                    $invoice->update(['scheduled_review_sent_at' => now()]);
                    $reviewed++;
                }
            });
        $queued = 0;
        $cancelled = 0;
        Invoice::query()->where('scheduled_email', true)->where('status', Invoice::STATUS_DRAFT)
            ->whereDate('issue_date', '<=', today())->whereNull('scheduled_email_queued_at')->with(['user', 'lines'])
            ->each(function (Invoice $invoice) use (&$queued, &$cancelled): void {
                try {
                    DB::transaction(function () use ($invoice): void {
                        $fresh = $invoice->fresh(['lines', 'tickets']);
                        if (! $fresh || ! app(\App\Services\Finance\InvoiceAllocationWorkspace::class)->isBalanced($fresh)) {
                            throw new UnbalancedInvoiceAllocation();
                        }
                        $invoice->update(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now(), 'scheduled_email_queued_at' => now(), 'scheduled_email_failure' => null, 'scheduled_email_failed_at' => null]);
                        app(WorkshopAllocation::class)->finaliseFundingInvoice($invoice->fresh('lines'), $invoice->created_by);
                    });
                    SendScheduledInvoiceEmail::dispatch((int) $invoice->id);
                    $queued++;
                } catch (UnbalancedInvoiceAllocation) {
                    app(\App\Services\ScheduledInvoiceCancellationService::class)->cancelForUnbalancedAllocation($invoice);
                    $cancelled++;
                } catch (Throwable $exception) {
                    report($exception);
                    $invoice->update(['status' => Invoice::STATUS_DRAFT, 'scheduled_email_queued_at' => null, 'scheduled_email_failed_at' => now(), 'scheduled_email_failure' => mb_substr($exception->getMessage(), 0, 5000)]);
                }
            });
        $this->info("Review notices: {$reviewed}; customer emails queued: {$queued}; schedules cancelled: {$cancelled}.");

        return self::SUCCESS;
    }
}
