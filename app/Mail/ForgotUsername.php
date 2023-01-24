<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ForgotUsername extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The list of usernames
     *
     * @var string[]
     */
    public $usernames;


    /**
     * Create a new message instance.
     *
     * @param array $usernames The usernames.
     * @return void
     */
    public function __construct(array $usernames)
    {
        $this->usernames = $usernames;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope()
    {
        return new Envelope(
            subject: '🤦 Forgot your username?',
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
            view: 'emails.user.forgot_username',
            text: 'emails.user.forgot_username_plain',
        );
    }
}
