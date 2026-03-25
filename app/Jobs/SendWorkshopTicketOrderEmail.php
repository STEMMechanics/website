<?php

namespace App\Jobs;

use App\Models\WorkshopTicketEmail;
use App\Services\WorkshopTicketOrderEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendWorkshopTicketOrderEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $workshopTicketEmailId)
    {
        $this->onQueue('mail');
    }

    public function handle(WorkshopTicketOrderEmailService $emailService): void
    {
        $workshopTicketEmail = WorkshopTicketEmail::query()->find($this->workshopTicketEmailId);
        if (! $workshopTicketEmail instanceof WorkshopTicketEmail) {
            return;
        }

        $emailService->queueCombinedEmail($workshopTicketEmail);
    }
}
