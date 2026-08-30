<?php

namespace App\Console\Commands;

use App\Jobs\SendEmail;
use App\Mail\WeeklyWorkplan;
use App\Services\AdminRecipientService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Console\Command;

class SendWeeklyWorkplanCommand extends Command
{
    protected $signature = 'workplan:send-weekly';

    protected $description = 'Send admins the consolidated Sunday workplan';

    public function handle(AdminRecipientService $admins, WeeklyWorkplanService $workplans): int
    {
        $workplan = $workplans->build();
        foreach ($admins->emails() as $email) {
            dispatch(new SendEmail($email, new WeeklyWorkplan($workplan)))->onQueue('mail');
        }
        $this->info('Queued weekly workplan for '.count($admins->emails()).' admin recipient(s).');

        return self::SUCCESS;
    }
}
