<?php

namespace App\Mail;

use App\Models\StoreOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class StoreOrderConfirmation extends Mailable
{
    use Queueable, SerializesModels;

    public bool $hasInvoiceAttachment;

    public int $receiptAttachmentCount;

    private array $attachmentFiles;

    public function __construct(
        public StoreOrder $order,
        public string $orderUrl,
        array $attachments = [],
    ) {
        $this->attachmentFiles = collect($attachments)->map(function ($attachment): array {
            $content = (string) ($attachment['content'] ?? '');

            return [
                'type' => (string) ($attachment['type'] ?? ''),
                'filename' => trim((string) ($attachment['filename'] ?? '')),
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
                'content_base64' => $content !== '' ? base64_encode($content) : '',
            ];
        })->values()->all();
        $this->hasInvoiceAttachment = collect($attachments)->contains(fn ($item) => (string) ($item['type'] ?? '') === 'invoice');
        $this->receiptAttachmentCount = (int) collect($attachments)->filter(fn ($item) => (string) ($item['type'] ?? '') === 'receipt')->count();
    }

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.order_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.order_from.name', (string) config('mail.from.name', '')));
        $subject = $this->order->isPaid()
            ? 'Your order '.$this->order->order_number.' is ready'
            : 'Your order '.$this->order->order_number.' has been created';

        $mail = $this
            ->subject($subject)
            ->markdown('emails.store-order-confirmation');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
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
