<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionUnsubscribed extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The email address.
     *
     * @var string
     */
    public $email;


    /**
     * Create a new message instance.
     *
     * @param string $email The email address.
     * @return void
     */
    public function __construct(string $email)
    {
        $this->email = $email;
    }

    /**
     * Get the message envelope.
     *
     * @return \Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'You have been unsubscribed',
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
            view: 'emails.user.subscription_unsubscribed',
            text: 'emails.user.subscription_unsubscribed_plain',
        );
    }
}
