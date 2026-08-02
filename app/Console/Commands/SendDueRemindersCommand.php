<?php

namespace App\Console\Commands;

use App\Jobs\SendReminder;
use App\Models\Reminder;
use App\Services\ReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

class SendDueRemindersCommand extends Command
{
    protected $signature = 'reminders:send-due {--limit=100}';

    protected $description = 'Send pending reminders whose scheduled time has arrived';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        Reminder::query()
            ->where('kind', ReminderService::WORKSHOP_TASK_KIND)
            ->where('status', Reminder::STATUS_PENDING)
            ->where('scheduled_at', '<', now()->subMinutes(5))
            ->update(['status' => Reminder::STATUS_CANCELLED]);

        $reminders = Reminder::query()
            ->where('status', Reminder::STATUS_PENDING)
            ->where('scheduled_at', '<=', now())
            ->orderBy('scheduled_at')
            ->limit($limit)
            ->get();

        $queued = 0;
        foreach ($reminders as $reminder) {
            $claimed = DB::transaction(function () use ($reminder): bool {
                return Reminder::query()
                    ->whereKey($reminder->id)
                    ->where('status', Reminder::STATUS_PENDING)
                    ->update([
                        'status' => Reminder::STATUS_QUEUED,
                        'queued_at' => now(),
                    ]) === 1;
            });

            if ($claimed) {
                try {
                    SendReminder::dispatch((int) $reminder->id);
                    $queued++;
                } catch (Throwable $exception) {
                    report($exception);
                    Reminder::query()->whereKey($reminder->id)->update([
                        'status' => Reminder::STATUS_PENDING,
                        'queued_at' => null,
                    ]);
                }
            }
        }

        $this->info('Queued '.$queued.' reminder'.($queued === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
