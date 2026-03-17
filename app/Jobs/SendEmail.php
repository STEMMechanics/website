<?php

namespace App\Jobs;

use App\Mail\ForumUnreadNotification;
use App\Models\SentEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

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
     * Sent email record id.
     *
     * @var string|null
     */
    public $sentEmailId;

    /**
     * Create a new job instance.
     *
     * @param  string  $to  The email receipient.
     * @param  Mailable  $mailable  The mailable.
     */
    public function __construct(string $to, Mailable $mailable)
    {
        $this->to = $to;
        $this->mailable = $mailable;
        $this->sentEmailId = null;
        $this->onQueue('mail');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Record attempted email before sending.
        $sentEmail = SentEmail::create([
            'recipient' => $this->to,
            'mailable_class' => get_class($this->mailable),
            'status' => SentEmail::STATUS_QUEUED,
        ]);

        $this->sentEmailId = $sentEmail->id;

        if (method_exists($this->mailable, 'withUnsubscribeLink')) {
            $unsubscribeRoute = $this->mailable instanceof ForumUnreadNotification
                ? 'unsubscribe.discussions'
                : 'unsubscribe';
            $unsubscribeLink = route($unsubscribeRoute, ['email' => $sentEmail->id]);
            $this->mailable->withUnsubscribeLink($unsubscribeLink);

            if (method_exists($this->mailable, 'unsubscribeHeaders')) {
                $unsubscribeHeaders = $this->mailable->unsubscribeHeaders();

                $this->mailable->withSymfonyMessage(function ($message) use ($unsubscribeHeaders) {
                    $headers = $message->getHeaders();

                    foreach ($unsubscribeHeaders as $name => $value) {
                        if (! $headers->has($name)) {
                            $headers->addTextHeader($name, $value);
                        }
                    }
                });
            }
        }

        try {
            Mail::to($this->to)->send($this->mailable);

            $sentEmail->update([
                'status' => SentEmail::STATUS_SENT,
                'sent_at' => now(),
                'failed_at' => null,
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $this->markFailed($exception);
            throw $exception;
        }
    }

    /**
     * Handle a job failure from the queue worker.
     */
    public function failed(Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    private function markFailed(Throwable $exception): void
    {
        if ($this->sentEmailId === null) {
            Log::error('Email send failed before SentEmail row existed', [
                'recipient' => $this->to,
                'mailable_class' => get_class($this->mailable),
                'error' => $exception->getMessage(),
            ]);

            return;
        }

        SentEmail::query()
            ->whereKey($this->sentEmailId)
            ->update([
                'status' => SentEmail::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 5000),
            ]);
    }
}
