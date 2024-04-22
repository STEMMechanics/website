<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class EmailUpdateLink extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $username;
    public $email;
    public $newEmail;

    public function __construct($token, $username, $email, $newEmail)
    {
        $this->token = $token;
        $this->username = $username;
        $this->email = $email;
        $this->newEmail = $newEmail;
    }

    public function build()
    {
        return $this
            ->subject('Confirm new email address')
            ->markdown('emails.change-email-link')
            ->with([
                'token' => $this->token,
                'username' => $this->username,
                'email' => $this->email,
                'newEmail' => $this->newEmail,
            ]);
    }
}
