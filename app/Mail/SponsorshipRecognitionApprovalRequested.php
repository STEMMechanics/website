<?php

namespace App\Mail;

use App\Models\Sponsor;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorshipRecognitionApprovalRequested extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Sponsor $sponsor,
        public string $adminUrl,
    ) {}

    public function build(): static
    {
        $this->sponsor->loadMissing('organisation');
        $label = trim($this->sponsor->publicLabel()) ?: 'Sponsor';

        return $this
            ->subject('Sponsor recognition needs approval: '.$label)
            ->markdown('emails.sponsorship-recognition-approval-requested');
    }
}
