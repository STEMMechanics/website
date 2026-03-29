<?php

namespace App\Services;

use App\Models\SiteOption;
use App\Models\Ticket;
use App\Models\Workshop;
use Carbon\Carbon;

class WorkshopTicketService
{
    public function holdWindowMinutes(): int
    {
        $configured = trim((string) SiteOption::value('tickets.hold-minutes', '10'));
        $minutes = is_numeric($configured) ? (int) $configured : 10;

        return max(1, min(240, $minutes));
    }

    public function cleanupExpiredHolds(?Workshop $workshop = null): int
    {
        $query = Ticket::query()
            ->where('status', Ticket::STATUS_HOLD)
            ->where('created_at', '<', now()->subMinutes($this->holdWindowMinutes()));

        if ($workshop) {
            $query->where('workshop_id', $workshop->id);
        }

        $deleted = $query->delete();

        if ($workshop instanceof Workshop) {
            $this->syncManagedTicketStatus($workshop);
        }

        return $deleted;
    }

    public function countReservedTickets(Workshop $workshop): int
    {
        $threshold = now()->subMinutes($this->holdWindowMinutes());

        return Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->where(function ($builder) use ($threshold) {
                $builder->whereIn('status', Ticket::activePurchasedStatuses())
                    ->orWhere(function ($holdQuery) use ($threshold) {
                    $holdQuery->where('status', Ticket::STATUS_HOLD)
                        ->where('created_at', '>=', $threshold);
                });
            })
            ->count();
    }

    public function availableTickets(Workshop $workshop): ?int
    {
        $maxTickets = $workshop->max_tickets;
        if ($maxTickets === null || $maxTickets < 1) {
            return null;
        }

        $reserved = $this->countReservedTickets($workshop);

        return max(0, ((int) $maxTickets) - $reserved);
    }

    public function canStartTicketCheckout(Workshop $workshop): bool
    {
        if (! in_array((string) $workshop->registration, ['tickets', 'classroom'], true)) {
            return false;
        }

//        if ($workshop->isPrivate() && ! $workshop->requiresPrivateTicketCode()) {
//            return false;
//        }

        $this->syncManagedTicketStatus($workshop);

        $status = trim((string) ($workshop->status ?? ''));
        if ($status !== 'open') {
            return false;
        }

        if ($workshop->closes_at && Carbon::parse($workshop->closes_at)->isPast()) {
            return false;
        }

        $available = $this->availableTickets($workshop);

        return $available === null || $available > 0;
    }

    public function syncManagedTicketStatus(Workshop $workshop): void
    {
        if (! in_array((string) $workshop->registration, ['tickets', 'classroom'], true)) {
            return;
        }

        $status = (string) ($workshop->status ?? '');
        if (! in_array($status, ['open', 'full'], true)) {
            return;
        }

        $available = $this->availableTickets($workshop);
        if ($available === null) {
            return;
        }

        if ($status === 'open' && $available <= 0) {
            $workshop->status = 'full';
            $workshop->save();
            return;
        }

        $canReopen = !($workshop->closes_at && Carbon::parse($workshop->closes_at)->isPast());
        if ($status === 'full' && $available > 0 && $canReopen) {
            $workshop->status = 'open';
            $workshop->save();
        }
    }

    public function ticketPriceAmount(Workshop $workshop): float
    {
        $raw = trim((string) ($workshop->price ?? ''));
        if ($raw === '') {
            return 0.0;
        }

        $normalized = strtolower($raw);
        if (in_array($normalized, ['free', 'tbd', 'tbc'], true)) {
            return 0.0;
        }

        $number = preg_replace('/[^0-9.]/', '', $raw);
        if (! is_string($number) || $number === '') {
            return 0.0;
        }

        return max(0, round((float) $number, 2));
    }
}
