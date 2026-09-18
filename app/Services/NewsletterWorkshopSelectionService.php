<?php

namespace App\Services;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class NewsletterWorkshopSelectionService
{
    public const WORKSHOP_LIMIT = 6;

    public function candidates(): Builder
    {
        return Workshop::query()
            ->with(['location', 'hero'])
            ->publiclyVisible()
            ->where(fn ($query) => $query->whereNull('workshops.is_private')->orWhere('workshops.is_private', false))
            ->whereIn('workshops.status', ['open', 'scheduled'])
            ->whereBetween('workshops.starts_at', [now()->addHours(6), now()->addDays(42)])
            ->orderBy('workshops.starts_at')
            ->orderBy('workshops.id');
    }

    /** @param array<int, string> $excludedIds @return Collection<int, Workshop> */
    public function selection(array $excludedIds): Collection
    {
        return $this->candidates()->whereNotIn('workshops.id', $excludedIds)->limit(self::WORKSHOP_LIMIT)->get();
    }
}
