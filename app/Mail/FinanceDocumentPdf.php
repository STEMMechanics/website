<?php

namespace App\Mail;

use App\Support\EmailMessageFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class FinanceDocumentPdf extends Mailable
{
    use Queueable, SerializesModels;

    public string $documentType;

    public string $documentNumber;

    public string $recipientName;

    public ?string $customMessage;

    public ?string $fullMessage;

    public ?float $documentTotal;

    public ?float $documentOutstanding;

    public ?string $documentDue;

    public ?string $resolvedFullMessage;

    public ?string $initiatedByEmail;

    public ?string $initiatedByName;

    public ?string $payUrl;

    public ?string $actionUrl;

    public ?string $actionLabel;

    private string $pdfContentBase64;

    private string $pdfFilename;

    public function __construct(
        string $documentType,
        string $documentNumber,
        string $recipientName,
        string $pdfContent,
        string $pdfFilename,
        ?string $customMessage = null,
        ?string $fullMessage = null,
        ?float $documentTotal = null,
        ?float $documentOutstanding = null,
        ?string $documentDue = null,
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null,
        ?string $payUrl = null,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ) {
        $this->documentType = $documentType;
        $this->documentNumber = $documentNumber;
        $this->recipientName = $recipientName;
        $this->pdfContentBase64 = $pdfContent !== '' ? base64_encode($pdfContent) : '';
        $this->pdfFilename = $pdfFilename;
        $normalizedCustomMessage = $customMessage !== null ? EmailMessageFormatter::normalizeForMarkdown($customMessage) : '';
        $normalizedFullMessage = $fullMessage !== null ? EmailMessageFormatter::normalizeForMarkdown($fullMessage) : '';
        $this->customMessage = $normalizedCustomMessage !== '' ? $normalizedCustomMessage : null;
        $this->fullMessage = $normalizedFullMessage !== '' ? $normalizedFullMessage : null;
        $this->documentTotal = $documentTotal;
        $this->documentOutstanding = $documentOutstanding;
        $this->documentDue = $documentDue !== null ? trim($documentDue) : null;
        $this->initiatedByEmail = $initiatedByEmail !== null ? trim($initiatedByEmail) : null;
        $this->initiatedByName = $initiatedByName !== null ? trim($initiatedByName) : null;
        $this->payUrl = $payUrl !== null ? trim($payUrl) : null;
        $this->actionUrl = $actionUrl !== null ? trim($actionUrl) : null;
        $this->actionLabel = $actionLabel !== null ? trim($actionLabel) : null;
        $this->resolvedFullMessage = $this->resolveFullMessage();
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));

        $mail = $this
            ->subject('Your '.ucfirst($this->documentType).' '.$this->documentNumber.' from STEMMechanics')
            ->markdown('emails.finance-document');

        $pdfBinary = base64_decode($this->pdfContentBase64, true);
        if ($pdfBinary !== false && $pdfBinary !== '') {
            $mail->attachData($pdfBinary, $this->pdfFilename, [
                'mime' => 'application/pdf',
            ]);
        }

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        return $mail;
    }

    private function resolveFullMessage(): ?string
    {
        $template = $this->fullMessage;
        if ($template === null || $template === '') {
            return null;
        }

        $recipientName = trim((string) $this->recipientName);
        $recipientFirstName = trim((string) strtok($recipientName, ' '));
        if ($recipientFirstName === '') {
            $recipientFirstName = $recipientName;
        }

        $totalFormatted = $this->documentTotal !== null ? '$'.number_format($this->documentTotal, 2) : '';
        $outstandingFormatted = $this->documentOutstanding !== null ? '$'.number_format($this->documentOutstanding, 2) : '';
        $dueFormatted = trim((string) ($this->documentDue ?? ''));

        return strtr($template, [
            '{{name}}' => $recipientFirstName,
            '{{id}}' => (string) $this->documentNumber,
            '{{total}}' => $totalFormatted,
            '{{outstanding}}' => $outstandingFormatted,
            '{{due}}' => $dueFormatted,
            '{{$total}}' => $totalFormatted,
            '{{$outstanding}}' => $outstandingFormatted,
            '{{$due}}' => $dueFormatted,
        ]);
    }
}
