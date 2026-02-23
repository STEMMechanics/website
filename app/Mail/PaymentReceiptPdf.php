<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptPdf extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;

    public string $invoiceNumber;

    public string $receiptNumber;

    public string $amount;

    public string $paidOn;

    public ?string $receiptUrl;

    public bool $isRefund;

    private string $pdfContentBase64;

    private string $pdfFilename;

    public function __construct(
        string $recipientName,
        string $invoiceNumber,
        string $receiptNumber,
        string $amount,
        string $paidOn,
        ?string $receiptUrl,
        bool $isRefund,
        string $pdfContent,
        string $pdfFilename
    ) {
        $this->recipientName = $recipientName;
        $this->invoiceNumber = $invoiceNumber;
        $this->receiptNumber = $receiptNumber;
        $this->amount = $amount;
        $this->paidOn = $paidOn;
        $this->receiptUrl = $receiptUrl;
        $this->isRefund = $isRefund;
        $this->pdfContentBase64 = $pdfContent !== '' ? base64_encode($pdfContent) : '';
        $this->pdfFilename = $pdfFilename;
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
        $fromKey = $this->isRefund ? 'refund_from' : 'payment_from';
        $fromAddress = trim((string) config('mail.'.$fromKey.'.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.'.$fromKey.'.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject(($this->isRefund ? 'Refund receipt ' : 'Payment receipt ').$this->receiptNumber)
            ->markdown('emails.payment-receipt');

        $pdfBinary = base64_decode($this->pdfContentBase64, true);
        if ($pdfBinary !== false && $pdfBinary !== '') {
            $mail->attachData($pdfBinary, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
        }

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        return $mail;
    }
}
