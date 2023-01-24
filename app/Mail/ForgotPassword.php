<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotPassword extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The user
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The reset code
     *
     * @var integer
     */
    public $code;


    /**
     * Create a new message instance.
     *
     * @param User    $user The user the email applies to.
     * @param integer $code The action code.
     * @return void
     */
    public function __construct(User $user, int $code)
    {
        $this->user = $user;
        $this->code = $code;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: '🤦 Forgot your password?',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content()
    {
        return new Content(
            view: 'emails.user.forgot_password',
            text: 'emails.user.forgot_password_plain',
        );
    }
}
