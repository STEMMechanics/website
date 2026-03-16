<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class InvoiceDocumentBundle extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;

    public string $invoiceNumber;

    public ?string $initiatedByEmail;

    public ?string $initiatedByName;

    public ?float $outstandingAmount;

    public ?string $payUrl;

    /**
     * @var array<int, array{filename:string,content_base64:string,mime?:string}>
     */
    private array $attachmentsPayload;

    /**
     * @param  array<int, array{filename:string,content:string,mime?:string}>  $attachments
     */
    public function __construct(
        string $recipientName,
        string $invoiceNumber,
        array $attachments,
        ?float $outstandingAmount = null,
        ?string $payUrl = null,
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null
    ) {
        $this->recipientName = $recipientName;
        $this->invoiceNumber = $invoiceNumber;
        $this->attachmentsPayload = collect($attachments)->map(function ($attachment): array {
            $content = (string) $attachment['content'];

            return [
                'filename' => trim((string) $attachment['filename']),
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
                'content_base64' => $content !== '' ? base64_encode($content) : '',
            ];
        })->values()->all();
        $this->outstandingAmount = $outstandingAmount;
        $this->payUrl = $payUrl !== null ? trim($payUrl) : null;
        $this->initiatedByEmail = $initiatedByEmail !== null ? trim($initiatedByEmail) : null;
        $this->initiatedByName = $initiatedByName !== null ? trim($initiatedByName) : null;
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));

        $mail = $this
            ->subject('Documents for invoice '.$this->invoiceNumber)
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

        if (! empty($this->initiatedByEmail)) {
            $mail->replyTo($this->initiatedByEmail, $this->initiatedByName ?: null);
            $mail->from($this->initiatedByEmail, $this->initiatedByName ?: null);
        }

        return $mail;
    }
}
