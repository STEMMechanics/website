<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserDelete extends Mailable
{
    use Queueable, SerializesModels;

    public $token;
    public $email;

    public function __construct($email)
    {
        $this->email = $email;
    }

    public function build()
    {
        return $this
            ->subject('Account Deletion Confirmation')
            ->markdown('emails.user-delete')
            ->with([
                'email' => $this->email,
            ]);
    }
}
