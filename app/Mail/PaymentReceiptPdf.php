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

    public string $paymentMethod;

    public ?string $receiptUrl;

    public bool $isRefund;

    public ?string $invoiceSummary;

    public ?string $statusSummary;

    public ?string $outstandingBeforeSummary;

    public ?string $appliedAmountSummary;

    public ?string $creditSummary;

    public ?float $creditAppliedAmount;

    public ?string $creditReferenceSummary;

    public ?float $orderTotalAmount;

    private string $pdfContentBase64;

    private string $pdfFilename;

    public function __construct(
        string $recipientName,
        string $invoiceNumber,
        string $receiptNumber,
        string $amount,
        string $paidOn,
        string $paymentMethod,
        ?string $receiptUrl,
        bool $isRefund,
        string $pdfContent,
        string $pdfFilename,
        ?string $invoiceSummary = null,
        ?string $statusSummary = null,
        ?string $outstandingBeforeSummary = null,
        ?string $appliedAmountSummary = null,
        ?string $creditSummary = null,
        ?float $creditAppliedAmount = null,
        ?string $creditReferenceSummary = null,
        ?float $orderTotalAmount = null
    ) {
        $this->recipientName = $recipientName;
        $this->invoiceNumber = $invoiceNumber;
        $this->receiptNumber = $receiptNumber;
        $this->amount = $amount;
        $this->paidOn = $paidOn;
        $this->paymentMethod = $paymentMethod;
        $this->receiptUrl = $receiptUrl;
        $this->isRefund = $isRefund;
        $this->invoiceSummary = $invoiceSummary;
        $this->statusSummary = $statusSummary;
        $this->outstandingBeforeSummary = $outstandingBeforeSummary;
        $this->appliedAmountSummary = $appliedAmountSummary;
        $this->creditSummary = $creditSummary;
        $this->creditAppliedAmount = $creditAppliedAmount !== null ? round(max(0, $creditAppliedAmount), 2) : null;
        $this->creditReferenceSummary = trim((string) ($creditReferenceSummary ?? '')) ?: null;
        $this->orderTotalAmount = $orderTotalAmount !== null ? round(max(0, $orderTotalAmount), 2) : null;
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
