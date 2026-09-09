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

    public ?array $equipmentOrder;

    public int $invoiceAttachmentCount;

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

    public int $participantAttachmentCount;

    public array $recommendedWorkshops = [];

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
        ?string $creditReferenceSummary = null,
        ?array $equipmentOrder = null
    ) {
        $this->equipmentOrder = $equipmentOrder;
        $this->invoiceAttachmentCount = collect($attachments)->where('type', 'invoice')->count();
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
        $this->participantAttachmentCount = (int) collect($attachments)->filter(fn ($item) => (string) ($item['type'] ?? '') === 'participant')->count();
        $this->ticketCount = max(0, (int) ($ticketCount ?? count($tickets)));
    }

    private function confirmationContent(): array
    {
        $documents = [];
        foreach ([
            'ticket' => $this->ticketAttachmentCount,
            'invoice' => $this->invoiceAttachmentCount,
            'payment receipt' => $this->receiptAttachmentCount,
            'credit receipt' => $this->creditReceiptAttachmentCount,
            'workshop document' => $this->participantAttachmentCount,
        ] as $label => $count) {
            if ($count > 0) {
                $documents[] = $label.($count > 1 ? 's' : '');
            }
        }
        $attachmentCount = $this->ticketAttachmentCount + $this->invoiceAttachmentCount + $this->receiptAttachmentCount + $this->creditReceiptAttachmentCount + $this->participantAttachmentCount;
        $documentList = count($documents) > 1
            ? implode(', ', array_slice($documents, 0, -1)).' and '.end($documents)
            : ($documents[0] ?? '');
        $attachmentSentence = $documentList !== ''
            ? 'Your '.$documentList.($attachmentCount === 1 ? ' is' : ' are').' attached to this email.'
            : '';
        $settled = ($this->invoice['status'] ?? '') === 'paid'
            || ($this->amount > 0 && $this->paymentAmount + $this->creditAppliedAmount >= $this->amount - 0.0001);
        $delivery = [];
        foreach ($this->equipmentOrder['shipments'] ?? [] as $shipment) {
            $primary = trim((string) ($shipment['title_primary'] ?? $shipment['title'] ?? ''));
            $primary = preg_replace('/^(Shipment|Collection)(?:\s+\d+)?:\s*/i', '', $primary) ?: $primary;
            $timing = trim((string) ($shipment['title_meta'] ?? ''));
            $arrival = trim((string) ($shipment['delivery_estimate_label'] ?? ''));
            $parts = array_filter([$primary, $timing]);
            if ($arrival !== '' && ! ($this->equipmentOrder['pickup'] ?? false)) {
                $parts[] = 'Estimated delivery: '.$arrival.($timing !== '' ? ' after dispatch' : '');
            }
            if ($parts !== []) {
                $delivery[] = implode(' · ', array_unique($parts));
            }
        }

        return [
            'firstName' => trim((string) strtok($this->recipientName, ' ')) ?: $this->recipientName,
            'attachments' => $attachmentSentence,
            'ticketHeading' => $this->ticketCount === 1 ? 'Your ticket' : 'Your tickets',
            'settled' => $settled,
            'free' => $this->amount <= 0.0001,
            'amountDue' => round(max(0, $this->amount - $this->paymentAmount - $this->creditAppliedAmount), 2),
            'delivery' => $delivery,
        ];
    }

    public function build(): static
    {
        $hasTicketContent = count($this->tickets) > 0 || $this->ticketAttachmentCount > 0;
        $workshopTitle = (string) ($this->workshop['title'] ?? 'your STEMMechanics order');
        $receiptAttachmentCount = $this->receiptAttachmentCount + $this->creditReceiptAttachmentCount;
        $subject = $hasTicketContent
            ? 'Your ticket'.($this->ticketCount > 1 ? 's' : '').($receiptAttachmentCount > 0 ? ' and receipt'.($receiptAttachmentCount > 1 ? 's' : '') : '').' for '.$workshopTitle
            : 'Your order details for '.$workshopTitle;
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
        $fromKey = $hasTicketContent ? 'ticket_from' : 'order_from';
        $fromAddress = trim((string) config('mail.'.$fromKey.'.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.'.$fromKey.'.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject($subject)
            ->view('emails.ticket-order-confirmation')
            ->text('emails.ticket-order-confirmation-text')
            ->with('confirmation', $this->confirmationContent());

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
