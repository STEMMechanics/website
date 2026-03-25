<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketCancelledNotice extends Mailable
{
    use Queueable, SerializesModels;

    public string $recipientName;

    public string $ticketReference;

    public string $workshopTitle;

    public string $workshopTime;

    public string $workshopLocation;

    public string $financialSummary;

    public string $documentSummary;

    public string $introLine;

    /**
     * @var array<int, array{filename:string,content_base64:string,mime?:string}>
     */
    private array $attachmentsPayload;

    public function __construct(
        string $recipientName,
        string $ticketReference,
        string $workshopTitle,
        string $workshopTime,
        string $workshopLocation,
        string $financialSummary,
        array $attachments = [],
        string $documentSummary = '',
        string $introLine = 'The following ticket has been cancelled.'
    ) {
        $this->recipientName = $recipientName;
        $this->ticketReference = $ticketReference;
        $this->workshopTitle = $workshopTitle;
        $this->workshopTime = $workshopTime;
        $this->workshopLocation = $workshopLocation;
        $this->financialSummary = $financialSummary;
        $this->documentSummary = trim($documentSummary);
        $this->introLine = trim($introLine) !== '' ? trim($introLine) : 'The following ticket has been cancelled.';
        $this->attachmentsPayload = collect($attachments)->map(function ($attachment): array {
            $content = (string) ($attachment['content'] ?? '');

            return [
                'filename' => trim((string) ($attachment['filename'] ?? '')),
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
                'content_base64' => $content !== '' ? base64_encode($content) : '',
            ];
        })->values()->all();
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
        $fromAddress = trim((string) config('mail.ticket_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.ticket_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject('Your ticket to '.$this->workshopTitle.' has been cancelled')
            ->markdown('emails.ticket-cancelled-notice');

        foreach ($this->attachmentsPayload as $attachment) {
            $filename = trim((string) ($attachment['filename'] ?? ''));
            $contentBase64 = (string) ($attachment['content_base64'] ?? '');
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

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        return $mail;
    }
}
