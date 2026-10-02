<?php

namespace App\Services;

use App\Jobs\SendEmail;
use App\Mail\ScheduledInvoiceFailure;
use App\Models\Invoice;

class ScheduledInvoiceCancellationService
{
    public const UNBALANCED_ALLOCATION_MESSAGE = 'Scheduled invoice was not sent because its cost centre allocation was not balanced. The schedule was cancelled and the invoice remains a draft. Resolve the Shortfall or Unallocated amount in the allocation panel, then schedule the invoice again.';

    public function cancelForUnbalancedAllocation(Invoice $invoice): void
    {
        $invoice->update([
            'status' => Invoice::STATUS_DRAFT,
            'issued_at' => null,
            'scheduled_email' => false,
            'scheduled_email_queued_at' => null,
            'scheduled_email_sent_at' => null,
            'scheduled_email_failed_at' => now(),
            'scheduled_email_failure' => self::UNBALANCED_ALLOCATION_MESSAGE,
        ]);

        $invoice = $invoice->fresh();
        if (! $invoice instanceof Invoice) {
            return;
        }

        foreach (app(AdminRecipientService::class)->emails() as $email) {
            dispatch(new SendEmail($email, new ScheduledInvoiceFailure($invoice, self::UNBALANCED_ALLOCATION_MESSAGE)))->onQueue('mail');
        }
    }
}
