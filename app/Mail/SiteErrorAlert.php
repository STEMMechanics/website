<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SiteErrorAlert extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Throwable $exception,
        public array $context = [],
    ) {}

    public function build(): static
    {
        $message = trim((string) $this->exception->getMessage());
        $message = $message !== '' ? $message : 'No exception message provided';

        return $this
            ->subject('Site error: '.class_basename($this->exception))
            ->markdown('emails.site-error-alert')
            ->with([
                'exceptionClass' => get_class($this->exception),
                'exceptionMessage' => $message,
                'requestMethod' => trim((string) ($this->context['requestMethod'] ?? '')) ?: null,
                'requestUrl' => trim((string) ($this->context['requestUrl'] ?? '')) ?: null,
                'requestUserAgent' => trim((string) ($this->context['requestUserAgent'] ?? '')) ?: null,
                'requestUserId' => trim((string) ($this->context['requestUserId'] ?? '')) ?: null,
                'requestUserEmail' => trim((string) ($this->context['requestUserEmail'] ?? '')) ?: null,
            ]);
    }
}
