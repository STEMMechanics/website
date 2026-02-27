<?php

namespace App\Console\Commands;

use App\Models\SquareWebhookEvent;
use App\Services\SquareWebhookSyncService;
use Illuminate\Console\Command;

class SyncSquareWebhooksCommand extends Command
{
    protected $signature = 'square:webhooks:sync
        {--only-unlinked : Process only events that are not linked to a local payment}
        {--limit=0 : Maximum number of events to process (0 = no limit)}
        {--event-id= : Reprocess one specific Square event_id}';

    protected $description = 'Replay stored Square webhook payloads and repair payment linkage/state';

    public function handle(SquareWebhookSyncService $syncService): int
    {
        $query = SquareWebhookEvent::query()->orderBy('id');

        $eventId = trim((string) $this->option('event-id'));
        if ($eventId !== '') {
            $query->where('event_id', $eventId);
        }

        if ((bool) $this->option('only-unlinked')) {
            $query->whereNull('payment_id');
        }

        $limit = max(0, (int) $this->option('limit'));
        if ($limit > 0) {
            $query->limit($limit);
        }

        $events = $query->get();

        $scanned = 0;
        $linked = 0;
        $createdPayments = 0;
        $updatedEvents = 0;
        $ignored = 0;
        $errors = 0;

        foreach ($events as $event) {
            $scanned++;
            $payload = is_array($event->payload) ? $event->payload : [];

            try {
                $result = $syncService->syncPayload($payload, $event);
                if ($result['ignored'] === true) {
                    $ignored++;
                }
                if ($result['payment'] !== null) {
                    $linked++;
                }
                if ($result['created_payment'] === true) {
                    $createdPayments++;
                }
                if ($result['event_updated'] === true) {
                    $updatedEvents++;
                }
            } catch (\Throwable $e) {
                $errors++;
                $this->error('Event #'.$event->id.' failed: '.$e->getMessage());
            }
        }

        $this->info('Square webhook sync complete.');
        $this->line('Scanned: '.$scanned);
        $this->line('Linked to payment: '.$linked);
        $this->line('Payments auto-created: '.$createdPayments);
        $this->line('Ignored by rule: '.$ignored);
        $this->line('Event rows updated: '.$updatedEvents);
        $this->line('Errors: '.$errors);

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
