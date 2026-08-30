<?php

namespace App\Mail;

use App\Services\WeeklyWorkplanPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WeeklyWorkplan extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $workplan) {}

    public function build(): static
    {
        $pdfs = app(WeeklyWorkplanPdfService::class);

        return $this->subject('Your STEMMechanics weekly workplan – '.$this->workplan['weekStart']->format('j M Y'))
            ->attachData($pdfs->render($this->workplan), $pdfs->filename($this->workplan), ['mime' => 'application/pdf'])
            ->markdown('emails.weekly-workplan');
    }
}
