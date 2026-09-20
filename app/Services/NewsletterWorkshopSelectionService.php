<?php

namespace App\Services;

use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;
use Carbon\CarbonInterface;

class NewsletterWorkshopSelectionService
{
    public const WORKSHOP_LIMIT = 6;

    public function nextRelease(): Carbon
    {
        $sendAt = now()->setTime(16, 0);
        if ($sendAt->dayOfWeek !== Carbon::WEDNESDAY || $sendAt->isPast()) {
            $sendAt->next(Carbon::WEDNESDAY)->setTime(16, 0);
        }

        return $sendAt;
    }

    public function candidates(?CarbonInterface $releaseAt = null): Builder
    {
        $releaseAt = $releaseAt ? $releaseAt->copy() : now();
        return Workshop::query()
            ->with(['location', 'hero'])
            ->publiclyVisible()
            ->where(fn ($query) => $query->whereNull('workshops.is_private')->orWhere('workshops.is_private', false))
            ->whereIn('workshops.status', ['open', 'scheduled'])
            ->whereBetween('workshops.starts_at', [$releaseAt->copy()->addHours(6), $releaseAt->copy()->addDays(42)])
            ->orderBy('workshops.starts_at')
            ->orderBy('workshops.id');
    }

    /** @param array<int, string> $excludedIds @return Collection<int, Workshop> */
    public function selection(array $excludedIds, ?CarbonInterface $releaseAt = null): Collection
    {
        return $this->candidates($releaseAt)->whereNotIn('workshops.id', $excludedIds)->limit(self::WORKSHOP_LIMIT)->get();
    }
}
