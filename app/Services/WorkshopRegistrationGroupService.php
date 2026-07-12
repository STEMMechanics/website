<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class WorkshopRegistrationGroupService
{
    /**
     * @param iterable<Ticket> $tickets
     */
    public function assignForTickets(iterable $tickets, ?string $fallbackUserId = null): int
    {
        if ($tickets instanceof EloquentCollection) {
            $collection = $tickets;
        } elseif (is_array($tickets)) {
            $collection = new EloquentCollection(array_values($tickets));
        } else {
            $collection = new EloquentCollection(iterator_to_array($tickets, false));
        }

        if ($collection->isEmpty()) {
            return 0;
        }

        $collection->loadMissing('workshop');

        $pairs = [];
        foreach ($collection as $ticket) {
            if (! in_array((int) $ticket->status, [
                Ticket::STATUS_PAID,
                Ticket::STATUS_PENDING_DOOR,
                Ticket::STATUS_PENDING_XFER,
                Ticket::STATUS_ACCOUNT,
            ], true)) {
                continue;
            }

            $workshop = $ticket->workshop;
            if (! $workshop instanceof Workshop || (string) $workshop->registration !== 'tickets') {
                continue;
            }

            $slug = UserGroup::normalizeSlug((string) ($workshop->ticket_group_slug ?? ''));
            if ($slug === '') {
                continue;
            }

            $userId = trim((string) ($ticket->user_id ?: $fallbackUserId));
            if ($userId === '') {
                continue;
            }

            $pairs[$userId.'|'.$slug] = [
                'user_id' => $userId,
                'slug' => $slug,
            ];
        }

        $created = 0;
        foreach ($pairs as $pair) {
            $group = UserGroup::query()->firstOrCreate($pair);
            if ($group->wasRecentlyCreated) {
                $created++;
            }
        }

        return $created;
    }
}
