<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Spatie\LaravelPdf\Facades\Pdf;

class UserLoginBackupCode extends Mailable
{
    use Queueable, SerializesModels;

    public $email;
    public function __construct($email)
    {
        $this->email = $email;
    }

    public function build()
    {
        return $this
            ->subject('Hey, did you recently log in?')
            ->markdown('emails.login-backup-code')
            ->with([
                'email' => $this->email,
            ]);
    }
}
