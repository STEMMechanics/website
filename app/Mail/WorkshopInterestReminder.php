<?php

namespace App\Mail;

use App\Models\Workshop;
use App\Models\WorkshopInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\URL;

class WorkshopInterestReminder extends Mailable
{
    use Queueable, SerializesModels;

    /** @param 'two_days'|'two_hours' $type */
    public function __construct(
        public Workshop $workshop,
        public WorkshopInterest $interest,
        public string $type,
        public bool $isSample = false,
    ) {}

    public function build(): static
    {
        $timing = $this->type === 'two_days' ? 'in 2 days' : 'in 2 hours';
        $subject = $this->isSample
            ? '[Sample] Workshop interest reminder: '.((string) ($this->workshop->title ?? 'Workshop'))
            : 'Coming up '.$timing.': '.((string) ($this->workshop->title ?? 'Workshop'));

        $mail = $this
            ->subject($subject)
            ->markdown('emails.workshop-interest-reminder')
            ->with([
                'unsubscribeUrl' => $this->interest->exists
                    ? URL::signedRoute('workshop-interest-reminders.unsubscribe', ['interest' => $this->interest])
                    : null,
            ]);

        $fromAddress = trim((string) config('mail.from.address', ''));
        $fromName = trim((string) config('mail.from.name', ''));
        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
