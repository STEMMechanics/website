<?php

namespace App\Observers;

use App\Models\Invoice;
use App\Services\SponsorshipService;

class SponsorshipInvoiceObserver
{
    public function updated(Invoice $invoice): void
    {
        if ($invoice->wasChanged('status') && (string) $invoice->status === Invoice::STATUS_PAID) {
            app(SponsorshipService::class)->syncPaidInvoice($invoice);
        }

        if ($invoice->wasChanged('status') && (string) $invoice->status === Invoice::STATUS_OVERDUE) {
            app(SponsorshipService::class)->syncOverdueInvoice($invoice);
        }
    }
}
