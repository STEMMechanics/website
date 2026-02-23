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
        return $this
            ->subject('Ticket lookup result')
            ->markdown('emails.ticket-no-tickets')
            ->with([
                'email' => $this->email,
            ]);
    }
}
