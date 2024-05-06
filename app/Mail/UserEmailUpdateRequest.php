<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserEmailUpdateRequest extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;
    public $newEmail;

    public function __construct($token, $email, $newEmail)
    {
        $this->token = $token;
        $this->email = $email;
        $this->newEmail = $newEmail;
    }

    public function build()
    {
        return $this
            ->subject('Almost There! Confirm Your New Email Address 👍')
            ->markdown('emails.email-update-request')
            ->with([
                'update_url' => route('update.email', ['token' => $this->token]),
                'email' => $this->email,
                'newEmail' => $this->newEmail,
            ]);
    }
}
