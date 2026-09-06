<?php

namespace App\Console\Commands;

use App\Jobs\SendAdminPush;
use App\Jobs\SendEmail;
use App\Mail\WeeklyWorkplan;
use App\Models\User;
use App\Services\AdminRecipientService;
use App\Services\WeeklyWorkplanService;
use Illuminate\Console\Command;

class SendWeeklyWorkplanCommand extends Command
{
    protected $signature = 'workplan:send-fortnightly';

    protected $description = 'Send admins the rolling fortnightly workplan each Sunday';

    public function handle(AdminRecipientService $admins, WeeklyWorkplanService $workplans): int
    {
        $recipients = $admins->emails(dashboardOnly: true);
        $workplan = $workplans->build();
        foreach ($recipients as $email) {
            dispatch(new SendEmail($email, new WeeklyWorkplan($workplan)))->onQueue('mail');
        }
        foreach (User::whereHas('groups', fn ($query) => $query->where('slug', 'admin'))->get() as $admin) {
            SendAdminPush::dispatch($admin->id, 'Your fortnightly workplan is ready', route('admin.dashboard'), 'fortnightly-workplan');
        }
        $this->info('Queued fortnightly workplan for '.count($recipients).' admin recipient(s).');

        return self::SUCCESS;
    }
}
