<?php

namespace App\Console\Commands;

use App\Jobs\SendEmail;
use App\Mail\MinecraftMessageFailureDigest;
use App\Models\MinecraftMessage;
use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class SendMinecraftMessageFailureAlertsCommand extends Command
{
    protected $signature = 'minecraft:messages:send-failure-alerts
        {--force : Ignore the quiet period and queue alerts immediately}';

    protected $description = 'Queue batched admin alerts for blocked Minecraft messages after a quiet period';

    public function handle(): int
    {
        if (! Schema::hasTable('minecraft_messages')) {
            return self::SUCCESS;
        }

        $force = (bool) $this->option('force');
        $delayMinutes = $this->notificationDelayMinutes();
        $pendingQuery = MinecraftMessage::query()
            ->where('passed', false)
            ->whereNull('admin_failure_notification_queued_at');

        $latestOccurredAt = $pendingQuery->max('occurred_at');
        if (! is_string($latestOccurredAt) || trim($latestOccurredAt) === '') {
            $this->info('No blocked Minecraft messages are waiting for an admin alert.');

            return self::SUCCESS;
        }

        if (! $force && Carbon::parse($latestOccurredAt)->isAfter(now()->subMinutes($delayMinutes))) {
            $this->info('Blocked Minecraft messages are still inside the quiet period. Use --force to queue the digest immediately.');

            return self::SUCCESS;
        }

        $messages = $pendingQuery
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get();
        if ($messages->isEmpty()) {
            $this->info('No blocked Minecraft messages are waiting for an admin alert.');

            return self::SUCCESS;
        }

        $recipients = $this->adminRecipients();
        if ($recipients === []) {
            $this->warn('No valid admin email recipients found for blocked Minecraft message alerts.');

            return self::SUCCESS;
        }

        $messagesUrl = route('admin.stemcraft.messages.index', ['status' => 'blocked']);
        foreach ($recipients as $recipient) {
            dispatch(new SendEmail($recipient, new MinecraftMessageFailureDigest($messages, $messagesUrl)))->onQueue('mail');
        }

        MinecraftMessage::query()
            ->whereIn('id', $messages->pluck('id'))
            ->update(['admin_failure_notification_queued_at' => now()]);

        $this->info('Queued blocked Minecraft message alerts for '.count($recipients).' recipient(s).');

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function adminRecipients(): array
    {
        $recipients = User::query()
            ->whereHas('groups', fn ($query) => $query->where('slug', 'admin'))
            ->pluck('email')
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();

        if ($recipients !== []) {
            return $recipients;
        }

        $fallback = strtolower(trim((string) config('mail.from.address', '')));

        return $fallback !== '' && filter_var($fallback, FILTER_VALIDATE_EMAIL)
            ? [$fallback]
            : [];
    }

    private function notificationDelayMinutes(): int
    {
        if (! Schema::hasTable('site_options')) {
            return 20;
        }

        $raw = trim((string) SiteOption::value(
            'minecraft.message-failure-notification-delay-minutes',
            SiteOption::defaultValue('minecraft.message-failure-notification-delay-minutes') ?? '20',
        ));

        return is_numeric($raw) ? max(1, (int) $raw) : 20;
    }
}
