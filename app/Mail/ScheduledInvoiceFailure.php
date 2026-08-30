<?php

namespace App\Mail;

use App\Models\Invoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ScheduledInvoiceFailure extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Invoice $invoice, public string $error) {}

    public function build(): static
    {
        return $this->subject('Action required: scheduled invoice '.$this->invoice->invoice_number.' was not sent')
            ->markdown('emails.scheduled-invoice-failure');
    }
}
