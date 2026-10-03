<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SponsorshipManageLink extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $manageUrl, public string $email) {}

    public function build(): static
    {
        return $this->subject('Manage your STEMMechanics sponsorship')
            ->markdown('emails.sponsorship-manage-link');
    }
}
