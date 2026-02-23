<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TicketMagicLink extends Mailable
{
    use Queueable, SerializesModels;

    public string $email;

    public string $magicUrl;

    public function __construct(string $token, string $email)
    {
        $this->email = $email;
        $this->magicUrl = route('tickets.magic', ['token' => $token]);
    }

    public function build(): static
    {
        $mail = $this
            ->subject('Access your ticket with this link')
            ->markdown('emails.ticket-magic-link')
            ->with([
                'email' => $this->email,
                'magicUrl' => $this->magicUrl,
            ]);

        $fromAddress = trim((string) config('mail.ticket_from.address', (string) config('mail.from.address', '')));
        $fromName = trim((string) config('mail.ticket_from.name', (string) config('mail.from.name', '')));

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
