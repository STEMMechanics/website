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
     * @var array<int, string>
     */
    private array $ccRecipients;

    /**
     * @param array<int, string> $bccRecipients
     * @param array<int, string> $ccRecipients
     */
    public function __construct(
        public string $subjectLine,
        string $workshopTitle,
        string $messageBody,
        array $ccRecipients = [],
        array $bccRecipients = [],
        ?string $initiatedByEmail = null,
        ?string $initiatedByName = null
    ) {
        $this->workshopTitle = trim($workshopTitle);
        $this->messageBody = EmailMessageFormatter::normalizeForMarkdown($messageBody);
        $this->ccRecipients = array_values($ccRecipients);
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

        if (count($this->ccRecipients) > 0) {
            $mail->cc($this->ccRecipients);
        }

        if (! empty($this->initiatedByEmail)) {
            $mail->from($this->initiatedByEmail, $this->initiatedByName ?: null);
        }

        return $mail;
    }
}
