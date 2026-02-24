<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketAttendeeUpdate extends Mailable
{
    use Queueable, SerializesModels;

    public string $mode;

    public string $recipientName;

    public string $purchaserName;

    public array $workshop;

    public array $ticket;

    private ?array $attachmentFile;

    public function __construct(
        string $mode,
        string $recipientName,
        string $purchaserName,
        array $workshop,
        array $ticket,
        ?array $attachment = null
    ) {
        $this->mode = $mode;
        $this->recipientName = $recipientName;
        $this->purchaserName = $purchaserName;
        $this->workshop = $workshop;
        $this->ticket = $ticket;

        if ($attachment) {
            $content = (string) ($attachment['content'] ?? '');
            $this->attachmentFile = [
                'filename' => trim((string) ($attachment['filename'] ?? 'ticket.pdf')),
                'mime' => (string) ($attachment['mime'] ?? 'application/pdf'),
                'content_base64' => $content !== '' ? base64_encode($content) : '',
            ];
        } else {
            $this->attachmentFile = null;
        }
    }

    public function build(): static
    {
        $subject = match ($this->mode) {
            'transferred_away' => 'Your workshop ticket has been transferred',
            'details_updated' => 'Your workshop ticket details were updated',
            'new_holder' => "You're in! Your workshop ticket",
            default => 'You have been issued a workshop ticket',
        };

        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));

        $mail = $this
            ->subject($subject)
            ->markdown('emails.ticket-attendee-update');

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        if (! $this->attachmentFile) {
            return $mail;
        }

        $binary = base64_decode((string) ($this->attachmentFile['content_base64'] ?? ''), true);
        $filename = trim((string) ($this->attachmentFile['filename'] ?? ''));
        if ($binary === false || $filename === '') {
            return $mail;
        }

        return $mail->attachData($binary, $filename, [
            'mime' => (string) ($this->attachmentFile['mime'] ?? 'application/pdf'),
        ]);
    }
}
