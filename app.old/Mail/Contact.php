<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class Contact extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * The contact name.
     *
     * @var string
     */
    public $name;

    /**
     * The contact email.
     *
     * @var string
     */
    public $email;

    /**
     * The contact content.
     *
     * @var string
     */
    public $content;


    /**
     * Create a new message instance.
     *
     * @param string $name    The contact name.
     * @param string $email   The contact email.
     * @param string $content The contact content.
     * @return void
     */
    public function __construct(string $name, string $email, string $content)
    {
        $this->name = $name;
        $this->email = $email;
        $this->content = $content;
    }

    /**
     * Get the message envelope.
     *
     * @return Illuminate\Mail\Mailables\Envelope
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: config('contact.contact_subject'),
        );
    }

    /**
     * Get the message content definition.
     *
     * @return Illuminate\Mail\Mailables\Content
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.user.contact',
            text: 'emails.user.contact_plain',
        );
    }
}
