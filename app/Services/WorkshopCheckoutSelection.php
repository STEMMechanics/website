<?php

namespace App\Services;

use App\Models\Workshop;
use Illuminate\Support\Collection;

class WorkshopCheckoutSelection
{
    public function supportsCombined(Workshop $workshop): bool
    {
        return $workshop->registration === 'tickets' && ! $workshop->usesClassroomRegistration()
            && ! $workshop->requiresPrivateTicketCode() && ! $workshop->isPrivate()
            && empty($workshop->optional_product_ids);
    }

    /** Public sessions can be combined across venues and online. */
    public function candidates(Workshop $anchor): Collection
    {
        if (! $this->supportsCombined($anchor)) {
            return collect();
        }

        return Workshop::query()->publiclyVisible()->where('registration', 'tickets')
            ->where('id', '!=', $anchor->id)->whereIn('status', ['open', 'full'])
            ->where('starts_at', '>=', now())
            ->where(fn ($query) => $query->whereNull('closes_at')->orWhere('closes_at', '>', now()))
            ->with(['location', 'hero'])->orderBy('starts_at')->get()
            ->filter(fn (Workshop $workshop) => $this->supportsCombined($workshop))
            ->values();
    }
}
