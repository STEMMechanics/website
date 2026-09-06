<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SiteErrorAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public string $exceptionClass, public array $context = []) {}

    public function build(): static
    {
        return $this->subject('Site error reference: '.($this->context['errorId'] ?? 'unknown'))
            ->markdown('emails.site-error-alert');
    }
}
