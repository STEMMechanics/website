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

    public function __construct(
        string $recipientName,
        string $ticketReference,
        string $workshopTitle,
        string $workshopTime,
        string $workshopLocation,
        string $financialSummary
    ) {
        $this->recipientName = $recipientName;
        $this->ticketReference = $ticketReference;
        $this->workshopTitle = $workshopTitle;
        $this->workshopTime = $workshopTime;
        $this->workshopLocation = $workshopLocation;
        $this->financialSummary = $financialSummary;
    }

    public function build(): static
    {
        $adminBcc = trim((string) config('mail.admin_bcc', 'admin@stemmechanics.com.au'));
        $fromAddress = trim((string) config('mail.ticket_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.ticket_from.name', (string) config('mail.from.name', '')));

        $mail = $this
            ->subject('Your ticket to "'.$this->workshopTitle.'" has been cancelled')
            ->markdown('emails.ticket-cancelled-notice');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        return $mail;
    }
}

