<?php

namespace App\Mail;

use App\Models\Workshop;
use App\Support\EmailMessageFormatter;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WorkshopWelcome extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Workshop $workshop) {}

    public function build(): static
    {
        $this->subject($this->workshop->welcome_subject)->markdown('emails.workshop-welcome', [
            'messageBody' => EmailMessageFormatter::normalizeForMarkdown((string) $this->workshop->welcome_body),
        ]);
        foreach ($this->workshop->files('welcome_attachments')->get() as $file) {
            $path = $file->path();
            if (! $path || ! is_file($path)) {
                throw new \RuntimeException('Welcome attachment is unavailable: '.$file->name);
            }
            $this->attach($path, ['as' => $file->name, 'mime' => $file->mime_type]);
        }

        return $this;
    }
}
