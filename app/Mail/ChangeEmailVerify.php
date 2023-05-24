<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangeEmailVerify extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The user instance.
     *
     * @var \App\Models\User
     */
    public $user;

    /**
     * The registration code.
     *
     * @var integer
     */
    public $code;

    /**
     * The new email address.
     *
     * @var string
     */
    public $new_email;


    /**
     * Create a new message instance.
     *
     * @param User    $user      The user the email applies to.
     * @param integer $code      The action code.
     * @param string  $new_email The new email address.
     * @return void
     */
    public function __construct(User $user, int $code, string $new_email)
    {
        $this->user = $user;
        $this->code = $code;
        $this->new_email = $new_email;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '👋🏻 Lets change your email!',
        );
    }

    /**
     * Get the message content definition.
     *
     * @return \Illuminate\Mail\Mailables\Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user.change_email_verify',
            text: 'emails.user.change_email_verify_plain',
        );
    }
}
