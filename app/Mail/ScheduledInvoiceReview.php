<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduledInvoiceReview extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice) {}

    public function build(): static
    {
        return $this->subject('Review scheduled invoice '.$this->invoice->invoice_number.' before tomorrow')
            ->markdown('emails.scheduled-invoice-review');
    }
}
