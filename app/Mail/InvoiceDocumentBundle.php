<?php

namespace App\Mail;

use App\Support\InvoiceEmailSubject;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceDocumentBundle extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;

    public string $invoiceNumber;

    public ?string $orderNumber;

    public ?string $initiatedByEmail;

    public ?string $initiatedByName;

    public ?float $outstandingAmount;

    public ?string $payUrl;

    public string $attachmentSummary;

    /**
     * @var array<int, array{filename:string,content_base64:string,mime?:string}>
     */
    private array $attachmentsPayload;

    /** @var array<int, string> */
    private array $documentTypes;

    /**
     * @param  array<int, array{filename:string,content:string,mime?:string}>  $attachments
     */
    public function __construct(
        string $recipientName,
        string $invoiceNumber,
        ?string $orderNumber,
        array $attachments,
        ?float $outstandingAmount = null,
        ?string $payUrl = null,
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null
    ) {
        $this->recipientName = $recipientName;
        $this->invoiceNumber = $invoiceNumber;
        $this->orderNumber = trim((string) ($orderNumber ?? '')) ?: null;
        $this->attachmentsPayload = collect($attachments)->map(function ($attachment): array {
            $content = (string) $attachment['content'];

            return [
                'filename' => trim((string) $attachment['filename']),
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
                'content_base64' => $content !== '' ? base64_encode($content) : '',
            ];
        })->values()->all();
        $this->documentTypes = collect($this->attachmentsPayload)
            ->map(fn (array $attachment): ?string => $this->documentTypeForFilename((string) $attachment['filename']))
            ->filter()
            ->values()
            ->all();
        $this->attachmentSummary = $this->buildAttachmentSummary();
        $this->outstandingAmount = $outstandingAmount;
        $this->payUrl = $payUrl !== null ? trim($payUrl) : null;
        $this->initiatedByEmail = $initiatedByEmail !== null ? trim($initiatedByEmail) : null;
        $this->initiatedByName = $initiatedByName !== null ? trim($initiatedByName) : null;
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));

        $subject = $this->orderNumber !== null
            ? 'Your order '.$this->orderNumber.' and invoice '.$this->invoiceNumber.' from STEMMechanics'
            : InvoiceEmailSubject::forInvoice($this->invoiceNumber, $this->documentTypes);

        $mail = $this
            ->subject($subject)
            ->markdown('emails.invoice-document-bundle');

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        foreach ($this->attachmentsPayload as $attachment) {
            $filename = trim((string) $attachment['filename']);
            $contentBase64 = (string) $attachment['content_base64'];
            if ($filename === '' || $contentBase64 === '') {
                continue;
            }

            $content = base64_decode($contentBase64, true);
            if ($content === false) {
                continue;
            }

            $mail->attachData($content, $filename, [
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
            ]);
        }

        return $mail;
    }

    private function documentTypeForFilename(string $filename): ?string
    {
        $filename = strtolower(basename($filename));

        return match (true) {
            str_starts_with($filename, 'refund-receipt-') => 'refund_receipt',
            str_starts_with($filename, 'payment-receipt-') => 'receipt',
            str_starts_with($filename, 'credit-applied-') => 'credit_receipt',
            str_starts_with($filename, 'tax-adjustment-') => 'tax_adjustment',
            str_starts_with($filename, 'ticket-') => 'ticket',
            str_starts_with($filename, 'invoice-') => 'invoice',
            default => null,
        };
    }

    private function buildAttachmentSummary(): string
    {
        if (count($this->attachmentsPayload) === 1 && count($this->documentTypes) === 1 && $this->documentTypes[0] === 'invoice') {
            return 'Attached is your invoice #'.$this->invoiceNumber.' for payment.';
        }

        $types = collect($this->documentTypes);
        $labels = [];
        $hasInvoice = $types->contains('invoice');

        if ($hasInvoice) {
            $labels[] = 'invoice #'.$this->invoiceNumber;
        }

        $receiptCount = $types->filter(fn (string $type): bool => in_array($type, ['receipt', 'refund_receipt', 'credit_receipt'], true))->count();
        if ($receiptCount > 0) {
            $labels[] = $receiptCount === 1 ? 'payment receipt' : 'payment receipts';
        }

        $taxAdjustmentCount = $types->filter(fn (string $type): bool => $type === 'tax_adjustment')->count();
        if ($taxAdjustmentCount > 0) {
            $labels[] = $taxAdjustmentCount === 1 ? 'tax adjustment note' : 'tax adjustment notes';
        }

        $ticketCount = $types->filter(fn (string $type): bool => $type === 'ticket')->count();
        if ($ticketCount > 0) {
            $labels[] = $ticketCount === 1 ? 'ticket' : 'tickets';
        }

        if ($labels === []) {
            return 'Attached are the documents for invoice #'.$this->invoiceNumber.'.';
        }

        $last = array_pop($labels);
        $description = $labels === [] ? $last : implode(', ', $labels).' and '.$last;

        return $hasInvoice
            ? 'Attached are your '.$description.'.'
            : 'Attached are the related documents for invoice #'.$this->invoiceNumber.': '.$description.'.';
    }
}
