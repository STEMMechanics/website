<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RegisterLink extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $username;
    public $email;

    public function __construct($token, $username, $email)
    {
        $this->token = $token;
        $this->username = $username;
        $this->email = $email;
    }

    public function build()
    {
        return $this
            ->subject('Here\'s your registration link')
            ->markdown('emails.register-link')
            ->with([
                'token' => $this->token,
                'username' => $this->username,
                'email' => $this->email,
            ]);
    }
}
