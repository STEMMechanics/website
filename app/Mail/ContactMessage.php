<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessage extends Mailable
{
    use Queueable, SerializesModels;

    public string $senderName;

    public string $senderEmail;

    public string $subjectLine;

    public string $messageBody;

    public ?string $senderUserId;

    public ?string $senderIp;

    public ?string $userAgent;

    public function __construct(
        string $senderName,
        string $senderEmail,
        string $subjectLine,
        string $messageBody,
        ?string $senderUserId = null,
        ?string $senderIp = null,
        ?string $userAgent = null
    ) {
        $this->senderName = $senderName;
        $this->senderEmail = $senderEmail;
        $this->subjectLine = $subjectLine;
        $this->messageBody = $messageBody;
        $this->senderUserId = $senderUserId !== null ? trim($senderUserId) : null;
        $this->senderIp = $senderIp !== null ? trim($senderIp) : null;
        $this->userAgent = $userAgent !== null ? trim($userAgent) : null;
    }

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.from.address', 'hello@example.com'));
        $fromName = trim((string) config('mail.from.name', config('app.name', 'STEMMechanics')));
        $adminBcc = trim((string) config('mail.admin_bcc', ''));

        $mail = $this
            ->from($fromAddress, $fromName)
            ->replyTo($this->senderEmail, $this->senderName)
            ->subject('Website contact: '.$this->subjectLine)
            ->markdown('emails.contact-message');

        if ($adminBcc !== '') {
            $mail->bcc($adminBcc);
        }

        return $mail;
    }
}
