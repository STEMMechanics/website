<?php

use App\Jobs\SendEmail;
use App\Mail\UpcomingWorkshops;
use App\Models\Invoice;
use App\Models\Media;
use App\Models\Ticket;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schedule;

/**
 * The scheduler is run from a cronjob on the server every minute.
 * To access the cronjob, run `crontab -u www-data -e` and add the following line:
 * * * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
 */
Artisan::command('email:send', function () {
    $subjects = [
        '🚀 Your STEM Adventure Awaits!',
        '⚡ Spark Your STEM Skills in a Workshop',
        '🔬 Unleash Your Curiosity in a Workshop',
        '🧠 Boost Your Brain with STEM Workshops',
        '🌟 Become a STEM Star: Join Our Workshops',
        '🔧 Tinker, Create, Learn in a Workshop',
        '🎨 Where Science Meets Creativity',
        '🏆 Level Up Your STEM Skills',
        '🌈 Discover the STEM Spectrum',
        '🔮 Future Innovators: Workshops Unveiled',
    ];

    $subject = $subjects[array_rand($subjects)];

    $subscribers = DB::table('email_subscriptions')
        ->whereNotNull('confirmed')
        ->get();

    foreach ($subscribers as $subscriber) {
        dispatch(new SendEmail($subscriber->email, new UpcomingWorkshops($subscriber->email, $subject)))->onQueue('mail');
    }
})->purpose('Send newsletter to confirmed subscribers')->weeklyOn(3, '16:00');

Artisan::command('cleanup', function () {

    // Clean up expired tokens
    DB::table('tokens')
        ->where('expires_at', '<', now())
        ->delete();

    // Published scheduled posts
    DB::table('posts')
        ->where('status', '!=', 'scheduled')
        ->where('published_at', '<', now())
        ->update(['status' => 'published']);

    // Close workshops
    DB::table('workshops')
        ->whereIn('status', ['open', 'full'])
        ->where('closes_at', '<', now())
        ->update(['status' => 'closed']);

    // Expire held workshop tickets older than 10 minutes.
    Ticket::query()
        ->where('status', Ticket::STATUS_HOLD)
        ->where('created_at', '<', now()->subMinutes(10))
        ->delete();

})->purpose('Clean up expired data')->everyMinute();

Artisan::command('regenerate-thumbnails', function () {
    $media = Media::all();

    foreach ($media as $m) {
        $m->generateVariants(false);
    }
})->purpose('Regenerate thumbnails');

Artisan::command('invoices:mark-overdue', function () {
    $updated = Invoice::query()
        ->where('total_amount', '>', 0)
        ->whereDate('due_date', '<', today())
        ->whereIn('status', [
            Invoice::STATUS_ISSUED,
            Invoice::STATUS_SENT,
        ])
        ->update([
            'status' => Invoice::STATUS_OVERDUE,
        ]);

    $this->info('Marked '.$updated.' invoice'.($updated === 1 ? '' : 's').' as overdue.');
})->purpose('Mark overdue invoices as overdue')
    ->hourly()
    ->withoutOverlapping();

Artisan::command('media:requeue-stuck {--minutes=15} {--limit=200} {--dry-run}', function () {
    $minutes = max(1, (int) $this->option('minutes'));
    $limit = max(1, (int) $this->option('limit'));
    $dryRun = (bool) $this->option('dry-run');

    $threshold = now()->subMinutes($minutes);

    $candidates = Media::query()
        ->whereIn('status', ['queued', 'processing'])
        ->where('updated_at', '<', $threshold)
        ->orderBy('updated_at')
        ->limit($limit)
        ->get();

    $checked = 0;
    $queued = 0;
    $ready = 0;
    $skipped = 0;

    foreach ($candidates as $media) {
        $checked++;

        $variantTypes = $media->getVariantTypes();
        if ($variantTypes === []) {
            $skipped++;

            continue;
        }

        $missing = false;
        foreach (array_keys($variantTypes) as $variantName) {
            if (! $media->hasVariant($variantName)) {
                $missing = true;
                break;
            }
        }

        if ($missing) {
            $queued++;
            if (! $dryRun) {
                $media->generateVariants(false);
            }

            continue;
        }

        $ready++;
        if (! $dryRun) {
            $media->status = 'ready';
            $media->save();
        }
    }

    $this->info('Checked: '.$checked);
    $this->info('Queued for regeneration: '.$queued);
    $this->info('Marked ready (already complete): '.$ready);
    $this->info('Skipped (unsupported type): '.$skipped);
    if ($dryRun) {
        $this->comment('Dry run mode: no records were modified.');
    }
})->purpose('Requeue stuck media variants and recover stale media statuses')
    ->everyTenMinutes()
    ->withoutOverlapping();

Schedule::command('database:backup')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('files:backup --incremental --window=24h')
    ->dailyAt('01:15')
    ->timezone((string) config('app.timezone', 'UTC'))
    ->withoutOverlapping();

Schedule::command('files:backup --full')
    ->monthlyOn(1, '02:15')
    ->timezone((string) config('app.timezone', 'UTC'))
    ->withoutOverlapping();

Schedule::command('store:orders:send-update-digests')
    ->dailyAt('20:00')
    ->timezone((string) config('app.timezone', 'UTC'))
    ->withoutOverlapping();

Schedule::command('store:products:send-low-stock-alerts')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('payments:send-pending-bank-transfer-reminders')
    ->dailyAt('08:00')
    ->timezone((string) config('app.timezone', 'UTC'))
    ->withoutOverlapping();
