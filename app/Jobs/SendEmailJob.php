<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

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
     */
    public function handle(): void
    {
        Mail::to($this->to)->send($this->mailable);
    }
}
