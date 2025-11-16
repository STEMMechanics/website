<?php

namespace App\Mail;

use App\Traits\HasUnsubscribeLink;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserWelcome extends Mailable
{
    use Queueable, SerializesModels, HasUnsubscribeLink;

    public $email;

    public function __construct($email)
    {
        $this->email = $email;
    }

    public function build()
    {
        return $this
            ->subject('Welcome to STEMMechanics 🌟')
            ->markdown('emails.welcome')
            ->with([
                'email' => $this->email,
                'unsubscribe' => $this->unsubscribeLink
            ]);
    }
}
