<?php

namespace App\Services;

use App\Models\SiteOption;
use App\Models\Workshop;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class NewsletterWorkshopSelectionService
{
    public const WORKSHOP_LIMIT = 6;

    public const SEND_DAY_OPTION = 'newsletter.send-day';

    public const SEND_TIME_OPTION = 'newsletter.send-time';

    public const DEFAULT_SEND_DAY = Carbon::WEDNESDAY;

    public const DEFAULT_SEND_TIME = '16:00';

    public function sendDay(): int
    {
        $value = $this->optionValue(self::SEND_DAY_OPTION, (string) self::DEFAULT_SEND_DAY);

        return in_array($value, ['0', '1', '2', '3', '4', '5', '6'], true)
            ? (int) $value
            : self::DEFAULT_SEND_DAY;
    }

    public function sendTime(): string
    {
        $value = $this->optionValue(self::SEND_TIME_OPTION, self::DEFAULT_SEND_TIME);

        return preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value) === 1
            ? $value
            : self::DEFAULT_SEND_TIME;
    }

    public function nextRelease(): Carbon
    {
        [$hour, $minute] = array_map('intval', explode(':', $this->sendTime()));
        $sendDay = $this->sendDay();
        $sendAt = now()->setTime($hour, $minute);
        if ($sendAt->dayOfWeek !== $sendDay || $sendAt->isPast()) {
            $sendAt->next($sendDay)->setTime($hour, $minute);
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

    private function optionValue(string $name, string $default): string
    {
        if (! Schema::hasTable('site_options')) {
            return $default;
        }

        return (string) (SiteOption::value($name, $default) ?? $default);
    }
}
