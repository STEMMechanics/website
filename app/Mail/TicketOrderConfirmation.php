<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketOrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;

    public array $workshop;

    public array $tickets;

    public string $paymentMethodLabel;

    public float $amount;

    public ?array $invoice;

    public bool $hasReceiptAttachment;

    public bool $hasCreditReceiptAttachment;

    public bool $hasInvoiceAttachment;

    public int $receiptAttachmentCount;

    public int $creditReceiptAttachmentCount;

    public float $creditAppliedAmount;

    public float $paymentAmount;

    public ?string $creditReferenceSummary;

    public int $ticketAttachmentCount;

    public int $ticketCount;

    private array $attachmentFiles;

    public function __construct(
        string $recipientName,
        array $workshop,
        array $tickets,
        string $paymentMethodLabel,
        float $amount,
        ?array $invoice,
        array $attachments = [],
        ?int $ticketCount = null,
        float $creditAppliedAmount = 0.0,
        float $paymentAmount = 0.0,
        ?string $creditReferenceSummary = null
    ) {
        $this->recipientName = $recipientName;
        $this->workshop = $workshop;
        $this->tickets = $tickets;
        $this->paymentMethodLabel = $paymentMethodLabel;
        $this->amount = $amount;
        $this->invoice = $invoice;
        $this->creditAppliedAmount = round(max(0, $creditAppliedAmount), 2);
        $this->paymentAmount = round(max(0, $paymentAmount), 2);
        $this->creditReferenceSummary = trim((string) ($creditReferenceSummary ?? '')) ?: null;
        $this->attachmentFiles = collect($attachments)->map(function ($attachment): array {
            $content = (string) ($attachment['content'] ?? '');

            return [
                'type' => (string) ($attachment['type'] ?? ''),
                'filename' => trim((string) ($attachment['filename'] ?? '')),
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
                'content_base64' => $content !== '' ? base64_encode($content) : '',
            ];
        })->values()->all();
        $this->hasReceiptAttachment = collect($attachments)->contains(fn ($item) => (string) ($item['type'] ?? '') === 'receipt');
        $this->hasCreditReceiptAttachment = collect($attachments)->contains(fn ($item) => (string) ($item['type'] ?? '') === 'credit_receipt');
        $this->hasInvoiceAttachment = collect($attachments)->contains(fn ($item) => (string) ($item['type'] ?? '') === 'invoice');
        $this->receiptAttachmentCount = (int) collect($attachments)->filter(fn ($item) => (string) ($item['type'] ?? '') === 'receipt')->count();
        $this->creditReceiptAttachmentCount = (int) collect($attachments)->filter(fn ($item) => (string) ($item['type'] ?? '') === 'credit_receipt')->count();
        $this->ticketAttachmentCount = (int) collect($attachments)->filter(fn ($item) => (string) ($item['type'] ?? '') === 'ticket')->count();
        $this->ticketCount = max(0, (int) ($ticketCount ?? count($tickets)));
    }

    public function build(): static
    {
        $isClassroomAccess = (string) ($this->workshop['registration'] ?? '') === 'classroom';
        $hasTicketContent = ! $isClassroomAccess && (count($this->tickets) > 0 || $this->ticketAttachmentCount > 0);
        $workshopTitle = (string) ($this->workshop['title'] ?? 'your STEMMechanics order');
        $receiptAttachmentCount = $this->receiptAttachmentCount + $this->creditReceiptAttachmentCount;
        $subject = $isClassroomAccess
            ? 'Your classroom access for '.$workshopTitle
            : ($hasTicketContent
            ? 'Your ticket' . ($this->ticketCount > 1 ? 's' : '') . ($receiptAttachmentCount > 0 ? ' and receipt'.($receiptAttachmentCount > 1 ? 's' : '') : '') . ' for '.$workshopTitle
            : 'Your order details for '.$workshopTitle);
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
        $fromKey = $isClassroomAccess ? 'order_from' : ($hasTicketContent ? 'ticket_from' : 'order_from');
        $fromAddress = trim((string) config('mail.'.$fromKey.'.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.'.$fromKey.'.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject($subject)
            ->markdown('emails.ticket-order-confirmation');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        foreach ($this->attachmentFiles as $attachment) {
            $content = trim((string) ($attachment['content_base64'] ?? ''));
            $filename = trim((string) ($attachment['filename'] ?? ''));
            if ($content === '' || $filename === '') {
                continue;
            }

            $binary = base64_decode($content, true);
            if ($binary === false) {
                continue;
            }

            $mail->attachData($binary, $filename, [
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
            ]);
        }

        return $mail;
    }
}
