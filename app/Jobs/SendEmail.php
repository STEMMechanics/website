<?php

namespace App\Jobs;

use App\Models\SentEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Mail to receipt
     *
     * @var string
     */
    public $to;

    /**
     * Mailable item
     *
     * @var Mailable
     */
    public $mailable;

    /**
     * Create a new job instance.
     *
     * @param string   $to       The email receipient.
     * @param Mailable $mailable The mailable.
     * @return void
     */
    public function __construct(string $to, Mailable $mailable)
    {
        $this->to       = $to;
        $this->mailable = $mailable;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Record sent email
        $sentEmail = SentEmail::create([
            'recipient' => $this->to,
            'mailable_class' => get_class($this->mailable)
        ]);

        // Add unsubscribe link if mailable supports it
        if (method_exists($this->mailable, 'withUnsubscribeLink')) {
            $unsubscribeLink = route('unsubscribe', ['email' => $sentEmail->id]);
            $this->mailable->withUnsubscribeLink($unsubscribeLink);
        }

        Mail::to($this->to)->send($this->mailable);
    }
}
