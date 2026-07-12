<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserLogin extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $name;
    public $email;

    public function __construct($token, $name, $email)
    {
        $this->token = $token;
        $this->name = $name;
        $this->email = $email;
    }

    public function build()
    {
        return $this
            ->subject('Here\'s your login link 🤫')
            ->markdown('emails.login')
            ->with([
                'login_url' => route('login', ['token' => $this->token]),
                'name' => $this->name,
                'email' => $this->email,
            ]);
    }
}
