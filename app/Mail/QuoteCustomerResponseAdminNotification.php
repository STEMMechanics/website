<?php

namespace App\Mail;

use App\Models\Quote;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class QuoteCustomerResponseAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Quote $quote,
        public string $responseStatus,
        public string $adminQuoteUrl,
        public ?string $adminInvoiceUrl = null,
        public ?string $adminOrderUrl = null,
        public bool $invoiceEmailed = false,
    ) {}

    public function build(): static
    {
        $responseLabel = match ($this->responseStatus) {
            Quote::STATUS_ACCEPTED => 'accepted',
            Quote::STATUS_CANCELLED => 'cancelled',
            default => trim((string) $this->responseStatus),
        };

        return $this
            ->subject('Quote '.$this->quote->quote_number.' was '.$responseLabel)
            ->markdown('emails.quote-customer-response-admin-notification');
    }
}
