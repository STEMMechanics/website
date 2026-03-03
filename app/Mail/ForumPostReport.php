<?php

namespace App\Mail;

use App\Models\ForumPost;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ForumPostReport extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly ForumPost $post,
        public readonly User $reporter,
        public readonly string $reason,
        public readonly string $postUrl,
    ) {
    }

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.from.address', 'hello@example.com'));
        $fromName = trim((string) config('mail.from.name', config('app.name', 'STEMMechanics')));
        $reporterEmail = trim((string) ($this->reporter->email ?? ''));
        $reporterName = trim((string) ($this->reporter->getName() ?: $this->reporter->username ?: 'Reporter'));

        $mail = $this
            ->from($fromAddress, $fromName)
            ->subject('Discussion post report: '.((string) $this->post->id))
            ->markdown('emails.forum-post-report');

        if ($reporterEmail !== '') {
            $mail->replyTo($reporterEmail, $reporterName);
        }

        return $mail;
    }
}
