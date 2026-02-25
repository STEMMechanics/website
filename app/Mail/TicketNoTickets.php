<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketNoTickets extends Mailable
{
    use Queueable, SerializesModels;

    public string $email;

    public function __construct(string $email)
    {
        $this->email = $email;
    }

    public function build(): static
    {
        $mail = $this
            ->subject('We couldn\'t find your tickets')
            ->markdown('emails.ticket-no-tickets')
            ->with([
                'email' => $this->email,
            ]);

        $fromAddress = trim((string) config('mail.ticket_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.ticket_from.name', (string) config('mail.from.name', '')));

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
