<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserRegister extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;

    public function __construct($token, $email)
    {
        $this->token = $token;
        $this->email = $email;
    }

    public function build()
    {
        return $this
            ->subject('Almost There! Just One More Step to Join Us 🚀')
            ->markdown('emails.register')
            ->with([
                'register_url' => route('register', ['token' => $this->token]),
                'email' => $this->email,
            ]);
    }
}
