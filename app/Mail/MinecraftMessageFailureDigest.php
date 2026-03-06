<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class MinecraftMessageFailureDigest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * @param  Collection<int, mixed>  $messages
     */
    public function __construct(
        public readonly Collection $messages,
        public readonly string $messagesUrl,
    ) {}

    public function build(): static
    {
        $fromAddress = trim((string) config('mail.from.address', 'hello@example.com'));
        $fromName = trim((string) config('mail.from.name', config('app.name', 'STEMMechanics')));

        return $this
            ->from($fromAddress, $fromName)
            ->subject('STEMCraft blocked messages: '.$this->messages->count())
            ->markdown('emails.minecraft-message-failure-digest');
    }
}
