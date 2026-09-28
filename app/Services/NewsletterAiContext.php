<?php

namespace App\Services;

use App\Models\Product;
use App\Models\SiteOption;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NewsletterAiContext
{
    public function __construct(
        private readonly NewsletterProductSelectionService $products,
        private readonly NewsletterWorkshopSelectionService $workshops,
    ) {}

    /** @return array<string, mixed> */
    public function header(?string $contentOrder = null): array
    {
        $selection = $this->products->selection();
        $releaseAt = $this->workshops->nextRelease();

        return [
            'content_order' => in_array($contentOrder, ['store', 'workshops'], true)
                ? $contentOrder
                : ($selection['content_order'] ?? 'store'),
            'selected_workshops' => $this->upcomingWorkshops($selection, $releaseAt),
            'selected_store_sections' => $this->selectedStoreSections($selection),
        ];
    }

    /** @return array<string, mixed> */
    public function message(): array
    {
        $selection = $this->products->selection();
        $now = now();
        $recentFrom = $now->copy()->subDays(14);
        $releaseAt = $this->workshops->nextRelease();
        $recentWorkshops = $this->recentWorkshops($recentFrom, $now);
        $allTimeCounts = $this->allTimeCounts($recentFrom, $now);

        return [
            'date_prepared' => $now->format('D j M Y'),
            'period' => [
                'recent_activity_from' => $recentFrom->format('Y-m-d'),
                'recent_activity_to' => $now->format('Y-m-d'),
            ],
            'recent_workshops' => $recentWorkshops,
            'upcoming_workshops' => $this->upcomingWorkshops($selection, $releaseAt),
            'new_or_restocked_store_products' => $this->newProducts($recentFrom),
            'selected_store_sections' => $this->selectedStoreSections($selection),
            'configured_holidays' => $this->holidays($recentFrom, $now->copy()->addDays(42)),
            'all_time_counts' => $allTimeCounts['counts'],
            'recent_milestones' => $allTimeCounts['milestones'],
        ];
    }

    /** @param array<string, mixed> $selection
     * @return list<array<string, string>>
     */
    private function upcomingWorkshops(array $selection, Carbon $releaseAt): array
    {
        return $this->workshops
            ->selection($selection['excluded_workshop_ids'] ?? [], $releaseAt)
            ->take(8)
            ->map(fn (Workshop $workshop): array => [
                'title' => (string) $workshop->title,
                'date' => $workshop->starts_at?->format('D j M Y g:ia') ?? '',
                'location' => $workshop->getLocationName(),
                'description' => Str::limit(strip_tags((string) ($workshop->summary ?: $workshop->content)), 400),
            ])->values()->all();
    }

    /** @param array<string, mixed> $selection
     * @return list<array{heading:string,introduction:string,products:list<array{title:string,description:string}>}>
     */
    private function selectedStoreSections(array $selection): array
    {
        return collect($selection['sections'] ?? [])->map(fn (array $section): array => [
            'heading' => (string) ($section['title'] ?? ''),
            'introduction' => (string) ($section['intro'] ?? ''),
            'products' => collect($section['products'] ?? [])->map(fn (Product $product): array => [
                'title' => (string) $product->title,
                'description' => Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 300),
            ])->values()->all(),
        ])->values()->all();
    }

    /** @return list<array<string, mixed>> */
    private function recentWorkshops(Carbon $from, Carbon $to): array
    {
        $workshops = Workshop::query()
            ->with('location')
            ->withCount([
                'tickets as registration_count' => fn (Builder $query): Builder => $query->whereIn('status', Ticket::activePurchasedStatuses()),
                'tickets as attended_count' => fn (Builder $query): Builder => $query->whereIn('status', Ticket::activePurchasedStatuses())->whereNotNull('attended_at'),
            ])
            ->where(fn (Builder $query): Builder => $query
                ->whereBetween('ends_at', [$from, $to])
                ->orWhere(fn (Builder $query): Builder => $query->whereNull('ends_at')->whereBetween('starts_at', [$from, $to])))
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->where(fn (Builder $query): Builder => $query->whereNull('is_private')->orWhere('is_private', false))
            ->orderByRaw('COALESCE(ends_at, starts_at) DESC')
            ->limit(12)
            ->get();

        $courseAttendance = [];
        if (Schema::hasTable('workshop_session_attendance') && $workshops->isNotEmpty()) {
            $courseAttendance = DB::table('workshop_session_attendance as attendance')
                ->join('tickets', 'tickets.id', '=', 'attendance.ticket_id')
                ->whereIn('attendance.workshop_id', $workshops->pluck('id'))
                ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
                ->select('attendance.workshop_id')
                ->selectRaw('COUNT(DISTINCT attendance.ticket_id) AS attended_count')
                ->groupBy('attendance.workshop_id')
                ->pluck('attended_count', 'attendance.workshop_id')
                ->all();
        }

        return $workshops->map(fn (Workshop $workshop): array => [
            'title' => (string) $workshop->title,
            'date' => $workshop->starts_at?->format('D j M Y') ?? '',
            'location' => $workshop->getLocationName(),
            'registrations' => (int) $workshop->getAttribute('registration_count'),
            'attended' => $workshop->isCourse()
                ? (int) ($courseAttendance[$workshop->id] ?? 0)
                : (int) $workshop->getAttribute('attended_count'),
        ])->values()->all();
    }

    /** @return list<array{title:string,availability:string,description:string}> */
    private function newProducts(Carbon $from): array
    {
        return Product::query()
            ->active()
            ->where(fn (Builder $query): Builder => $query
                ->where('created_at', '>=', $from)
                ->orWhere('restocked_at', '>=', $from))
            ->orderByDesc('created_at')
            ->get(['title', 'short_description', 'description', 'created_at', 'restocked_at'])
            ->filter(fn (Product $product): bool => $product->isSelectionPurchasable()
                || $product->purchasableVariants()->contains(fn ($variant): bool => $product->isSelectionPurchasable($variant)))
            ->take(12)
            ->map(fn (Product $product): array => [
                'title' => (string) $product->title,
                'availability' => $product->created_at?->gte($from) ? 'Newly added' : 'Recently restocked',
                'description' => Str::limit(strip_tags((string) ($product->short_description ?: $product->description)), 300),
            ])->values()->all();
    }

    /** @return list<array{type:string,label:string,from:string,to:string}> */
    private function holidays(Carbon $from, Carbon $to): array
    {
        return collect([
            ['type' => 'school', 'label' => (string) SiteOption::value('workshops.school-holidays-label', 'School holidays'), 'value' => (string) SiteOption::value('workshops.school-holidays', '')],
            ['type' => 'public', 'label' => 'Public holiday', 'value' => (string) SiteOption::value('workshops.public-holidays', '')],
        ])->flatMap(function (array $source) use ($from, $to): array {
            return collect(preg_split('/\R/', $source['value']) ?: [])->map(function (string $line) use ($source, $from, $to): ?array {
                $line = trim($line);
                if ($line === '') {
                    return null;
                }

                if ($source['type'] === 'public') {
                    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s*\|\s*(.+))?$/', $line, $matches) !== 1) {
                        return null;
                    }
                    $start = $this->parseDate($matches[1]);
                    $end = $start?->copy();
                    $label = trim($matches[2] ?? '') ?: $source['label'];
                } else {
                    if (preg_match('/^(\d{4}-\d{2}-\d{2})(?:\s*(?:to|-)\s*(\d{4}-\d{2}-\d{2}))?$/', $line, $matches) !== 1) {
                        return null;
                    }
                    $start = $this->parseDate($matches[1]);
                    $end = $this->parseDate($matches[2] ?? $matches[1]);
                    $label = trim($source['label']) ?: 'School holidays';
                }

                if (! $start || ! $end || $end->lt($from) || $start->gt($to)) {
                    return null;
                }

                return [
                    'type' => $source['type'],
                    'label' => $label,
                    'from' => $start->toDateString(),
                    'to' => $end->toDateString(),
                ];
            })->filter()->all();
        })->take(16)->values()->all();
    }

    private function parseDate(string $date): ?Carbon
    {
        try {
            $parsed = Carbon::createFromFormat('!Y-m-d', $date, config('app.timezone'));

            return $parsed && $parsed->format('Y-m-d') === $date ? $parsed : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array{counts:array{completed_workshops:int,active_registrations:int},milestones:list<array{metric:string,value:int}>} */
    private function allTimeCounts(Carbon $since, Carbon $now): array
    {
        $completedWorkshops = Workshop::query()
            ->whereRaw('COALESCE(ends_at, starts_at) <= ?', [$now])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->count();
        $completedBefore = Workshop::query()
            ->whereRaw('COALESCE(ends_at, starts_at) < ?', [$since])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->count();

        $completedWorkshopIds = Workshop::query()
            ->whereRaw('COALESCE(ends_at, starts_at) <= ?', [$now])
            ->whereNotIn('status', ['cancelled', 'draft'])
            ->select('id');
        $registrations = Ticket::query()->whereIn('status', Ticket::activePurchasedStatuses())
            ->whereIn('workshop_id', $completedWorkshopIds)->count();
        $registrationsBefore = Ticket::query()->whereIn('status', Ticket::activePurchasedStatuses())
            ->where('created_at', '<', $since)
            ->whereIn('workshop_id', $completedWorkshopIds)->count();

        $milestones = array_values(array_filter([
            $this->crossedMilestone('workshops_delivered', $completedBefore, $completedWorkshops, [10, 25, 50, 100, 250, 500, 1000]),
            $this->crossedMilestone('active_ticket_registrations', $registrationsBefore, $registrations, [25, 50, 100, 250, 500, 1000, 2500, 5000]),
        ]));

        return [
            'counts' => [
                'completed_workshops' => $completedWorkshops,
                'active_registrations' => $registrations,
            ],
            'milestones' => $milestones,
        ];
    }

    /** @param list<int> $thresholds
     * @return array{metric:string,value:int}|null
     */
    private function crossedMilestone(string $metric, int $before, int $current, array $thresholds): ?array
    {
        $crossed = collect($thresholds)->filter(fn (int $threshold): bool => $before < $threshold && $current >= $threshold)->max();

        return $crossed ? ['metric' => $metric, 'value' => (int) $crossed] : null;
    }
}
