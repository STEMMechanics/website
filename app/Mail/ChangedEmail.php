<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ChangedEmail extends Mailable
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
     * The old email.
     *
     * @var string
     */
    public $old_email;

    /**
     * The new email.
     *
     * @var string
     */
    public $new_email;


    /**
     * Create a new message instance.
     *
     * @param User   $user      The user the email applies to.
     * @param string $old_email The previous email address.
     * @param string $new_email The new email address.
     * @return void
     */
    public function __construct(User $user, string $old_email, string $new_email)
    {
        $this->user = $user;
        $this->old_email = $old_email;
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
            subject: '👍 Your email has been changed!',
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
            view: 'emails.user.changed_email',
            text: 'emails.user.changed_email_plain',
        );
    }
}
