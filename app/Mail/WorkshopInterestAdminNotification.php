<?php

namespace App\Mail;

use App\Models\Workshop;
use App\Models\WorkshopInterest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkshopInterestAdminNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Workshop $workshop,
        public WorkshopInterest $interest,
        public string $adminUrl,
        public string $publicUrl,
    ) {}

    public function build(): static
    {
        $this->interest->loadMissing('user.parent');

        $fromAddress = trim((string) config('mail.from.address', ''));
        $fromName = trim((string) config('mail.from.name', ''));

        $mail = $this
            ->subject('Workshop interest registered: '.((string) ($this->workshop->title ?? 'Workshop')))
            ->markdown('emails.workshop-interest-admin-notification');

        if ($fromAddress !== '') {
            $mail->from($fromAddress, $fromName !== '' ? $fromName : null);
        }

        return $mail;
    }
}
