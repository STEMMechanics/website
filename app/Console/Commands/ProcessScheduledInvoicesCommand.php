<?php

namespace App\Console\Commands;

use App\Jobs\SendEmail;
use App\Jobs\SendScheduledInvoiceEmail;
use App\Mail\ScheduledInvoiceReview;
use App\Models\Invoice;
use App\Services\AdminRecipientService;
use Illuminate\Console\Command;
use Throwable;

class ProcessScheduledInvoicesCommand extends Command
{
    protected $signature = 'invoices:process-scheduled';

    protected $description = 'Send review notices and issue scheduled invoices';

    public function handle(AdminRecipientService $admins): int
    {
        $reviewed = 0;
        Invoice::query()->where('scheduled_email', true)->where('status', Invoice::STATUS_DRAFT)
            ->whereDate('issue_date', today()->addDay())->whereNull('scheduled_review_sent_at')->with('user')
            ->each(function (Invoice $invoice) use ($admins, &$reviewed): void {
                $recipients = $admins->emails();
                foreach ($recipients as $email) {
                    dispatch(new SendEmail($email, new ScheduledInvoiceReview($invoice)))->onQueue('mail');
                }
                if ($recipients !== []) {
                    $invoice->update(['scheduled_review_sent_at' => now()]);
                    $reviewed++;
                }
            });
        $queued = 0;
        Invoice::query()->where('scheduled_email', true)->where('status', Invoice::STATUS_DRAFT)
            ->whereDate('issue_date', '<=', today())->whereNull('scheduled_email_queued_at')->with(['user', 'lines'])
            ->each(function (Invoice $invoice) use (&$queued): void {
                try {
                    $invoice->update(['status' => Invoice::STATUS_ISSUED, 'issued_at' => now(), 'scheduled_email_queued_at' => now(), 'scheduled_email_failure' => null, 'scheduled_email_failed_at' => null]);
                    SendScheduledInvoiceEmail::dispatch((int) $invoice->id);
                    $queued++;
                } catch (Throwable $exception) {
                    report($exception);
                    $invoice->update(['status' => Invoice::STATUS_DRAFT, 'scheduled_email_queued_at' => null, 'scheduled_email_failed_at' => now(), 'scheduled_email_failure' => mb_substr($exception->getMessage(), 0, 5000)]);
                }
            });
        $this->info("Review notices: {$reviewed}; customer emails queued: {$queued}.");

        return self::SUCCESS;
    }
}
