<?php

namespace App\Console\Commands;

use App\Services\WorkshopInterestReminderService;
use Illuminate\Console\Command;

class SendWorkshopInterestRemindersCommand extends Command
{
    protected $signature = 'workshops:send-interest-reminders';

    protected $description = 'Queue upcoming workshop reminders for people who registered interest';

    public function handle(WorkshopInterestReminderService $service): int
    {
        $queued = $service->queueDue();
        $this->info('Queued '.$queued.' workshop interest reminder'.($queued === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
