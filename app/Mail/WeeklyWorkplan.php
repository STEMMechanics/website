<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklyWorkplan extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $workplan) {}

    public function build(): static
    {
        return $this->subject('Your STEMMechanics weekly workplan – '.$this->workplan['weekStart']->format('j M Y'))
            ->markdown('emails.weekly-workplan');
    }
}
