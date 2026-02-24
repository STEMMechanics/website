<?php

namespace App\Mail;

use App\Support\EmailMessageFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkshopTicketBroadcast extends Mailable
{
    use Queueable, SerializesModels;

    public string $workshopTitle;

    public string $messageBody;

    public ?string $initiatedByEmail;

    public ?string $initiatedByName;

    /**
     * @var array<int, string>
     */
    private array $bccRecipients;

    /**
     * @param array<int, string> $bccRecipients
     */
    public function __construct(
        public string $subjectLine,
        string $workshopTitle,
        string $messageBody,
        array $bccRecipients = [],
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null
    ) {
        $this->workshopTitle = trim($workshopTitle);
        $this->messageBody = EmailMessageFormatter::normalizeForMarkdown($messageBody);
        $this->bccRecipients = array_values($bccRecipients);
        $this->initiatedByEmail = $initiatedByEmail !== null ? trim($initiatedByEmail) : null;
        $this->initiatedByName = $initiatedByName !== null ? trim($initiatedByName) : null;
    }

    public function build(): static
    {
        $mail = $this
            ->subject($this->subjectLine)
            ->markdown('emails.workshop-ticket-broadcast');

        if (count($this->bccRecipients) > 0) {
            $mail->bcc($this->bccRecipients);
        }

        if (! empty($this->initiatedByEmail)) {
            $mail->replyTo($this->initiatedByEmail, $this->initiatedByName ?: null);
            $mail->from($this->initiatedByEmail, $this->initiatedByName ?: null);
        }

        return $mail;
    }
}
