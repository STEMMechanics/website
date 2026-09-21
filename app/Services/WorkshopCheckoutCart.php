<?php

namespace App\Services;

use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Support\Carbon;

class WorkshopCheckoutCart
{
    public function activeFor(Workshop $workshop): ?Workshop
    {
        $selection = app(WorkshopCheckoutSelection::class);
        if (! $selection->supportsCombined($workshop)) {
            return null;
        }
        $eligible = collect($this->bookings())->reverse()->map(function ($booking) use ($selection) {
            $checkout = session('ticket_checkout_flow.'.$booking['id']);
            $ids = $checkout['workshop_ids'] ?? [$booking['id']];
            $workshops = Workshop::whereIn('id', $ids)->get();
            if ($workshops->count() !== count($ids) || ! $workshops->every(fn (Workshop $item) => $selection->supportsCombined($item) && $item->isPubliclyVisible()
                && in_array($item->status, ['open', 'full'], true)
                && (! $item->closes_at || $item->closes_at->isFuture())
            )) {
                return;
            }

            return $workshops->firstWhere('id', $booking['id']);
        })->filter();

        return $eligible->first(fn (Workshop $anchor) => in_array($workshop->id, session('ticket_checkout_flow.'.$anchor->id.'.workshop_ids', [$anchor->id]), true))
            ?? $eligible->first();
    }

    /** Active reservations belong to the current browser session, including guest bookings. */
    public function bookings(): array
    {
        $bookings = [];
        foreach (session('ticket_checkout_flow', []) as $anchorId => $checkout) {
            if (! is_array($checkout) || ($checkout['payment_complete'] ?? false) || empty($checkout['expires_at'])) {
                continue;
            }
            $expires = Carbon::parse($checkout['expires_at']);
            if ($expires->isPast()) {
                continue;
            }
            $ids = $checkout['hold_ids'] ?? [];
            if ($ids === []) {
                continue;
            }
            $tickets = Ticket::with('workshop')->whereIn('id', $ids)->where('status', Ticket::STATUS_HOLD)->get();
            $anchor = Workshop::find($anchorId);
            if (! $anchor || $tickets->count() !== count($ids)) {
                continue;
            }
            $service = app(WorkshopTicketService::class);
            if ($tickets->contains(fn (Ticket $ticket) => ! $ticket->workshop || $ticket->created_at->lt(now()->subMinutes($service->holdWindowMinutes($ticket->workshop))))) {
                continue;
            }
            $bookings[] = [
                'id' => (string) $anchorId,
                'title' => $tickets->pluck('workshop.title')->unique()->join(', '),
                'count' => isset($checkout['review_draft'])
                    ? collect($checkout['review_draft'])->sum(fn ($person) => count(array_intersect($person['workshops'], $checkout['workshop_ids']))) : $tickets->count(),
                'selection_pending' => isset($checkout['review_draft']),
                'expires_at' => $expires->toIso8601String(),
                'url' => route(app(WorkshopCheckoutSelection::class)->supportsCombined($anchor)
                    ? 'workshop.ticket.flow.cart' : 'workshop.ticket.flow.payment', $anchor),
            ];
        }

        return $bookings;
    }
}
