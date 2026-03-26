<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoicePaymentLink extends Mailable
{
    use Queueable, SerializesModels;

    public string $invoiceNumber;

    public string $recipientName;

    public string $payUrl;

    public string $pdfUrl;

    public ?string $customMessage;

    public ?string $initiatedByEmail;

    public ?string $initiatedByName;

    public function __construct(
        string $invoiceNumber,
        string $recipientName,
        string $payUrl,
        string $pdfUrl,
        ?string $customMessage = null,
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null
    ) {
        $this->invoiceNumber = $invoiceNumber;
        $this->recipientName = $recipientName;
        $this->payUrl = $payUrl;
        $this->pdfUrl = $pdfUrl;
        $this->customMessage = $customMessage !== null ? trim($customMessage) : null;
        $this->initiatedByEmail = $initiatedByEmail !== null ? trim($initiatedByEmail) : null;
        $this->initiatedByName = $initiatedByName !== null ? trim($initiatedByName) : null;
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));

        $mail = $this
            ->subject('Invoice '.$this->invoiceNumber.' payment link')
            ->markdown('emails.invoice-payment-link');

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        if (! empty($this->initiatedByEmail)) {
            $mail->from($this->initiatedByEmail, $this->initiatedByName ?: null);
        }

        return $mail;
    }
}
