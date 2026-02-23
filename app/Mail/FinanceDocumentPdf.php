<?php

namespace App\Mail;

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

    public ?string $initiatedByEmail;

    public ?string $initiatedByName;

    public ?string $payUrl;

    private string $pdfContentBase64;

    private string $pdfFilename;

    public function __construct(
        string $documentType,
        string $documentNumber,
        string $recipientName,
        string $pdfContent,
        string $pdfFilename,
        ?string $customMessage = null,
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null,
        ?string $payUrl = null
    ) {
        $this->documentType = $documentType;
        $this->documentNumber = $documentNumber;
        $this->recipientName = $recipientName;
        $this->pdfContentBase64 = $pdfContent !== '' ? base64_encode($pdfContent) : '';
        $this->pdfFilename = $pdfFilename;
        $this->customMessage = $customMessage !== null ? trim($customMessage) : null;
        $this->initiatedByEmail = $initiatedByEmail !== null ? trim($initiatedByEmail) : null;
        $this->initiatedByName = $initiatedByName !== null ? trim($initiatedByName) : null;
        $this->payUrl = $payUrl !== null ? trim($payUrl) : null;
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));

        $mail = $this
            ->subject(ucfirst($this->documentType).' '.$this->documentNumber.' from STEMMechanics')
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

        if (! empty($this->initiatedByEmail)) {
            $mail->replyTo($this->initiatedByEmail, $this->initiatedByName ?: null);
            $mail->from($this->initiatedByEmail, $this->initiatedByName ?: null);
        }

        return $mail;
    }
}
