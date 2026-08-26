<?php

namespace App\Services;

use App\Models\Location;
use App\Models\Organisation;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class WorkshopRecommendationService
{
    /** @return Collection<int, Workshop> */
    public function forWorkshop(Workshop $source, int $limit = 3): Collection
    {
        $source->loadMissing(['location', 'categories', 'hostedFor.parent']);
        $organisationIds = $this->organisationFamilyIds($source);
        $categoryIds = $source->categories->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $candidates = Workshop::query()
            ->publiclyVisible()
            ->whereKeyNot($source->getKey())
            ->where('is_private', false)
            ->whereNotIn('status', ['cancelled', 'closed', 'draft'])
            ->where('starts_at', '>=', now())
            ->with(['hero', 'location', 'categories', 'hostedFor.parent'])
            ->limit(250)
            ->get();

        return $candidates
            ->sortBy(fn (Workshop $candidate): array => $this->score($source, $candidate, $organisationIds, $categoryIds))
            ->take(max(0, $limit))
            ->values();
    }

    /** @return Collection<int, Workshop> */
    public function forSuburb(string $suburb): Collection
    {
        return Workshop::query()
            ->publiclyVisible()
            ->where('is_private', false)
            ->whereNotIn('status', ['cancelled', 'closed', 'draft'])
            ->where('starts_at', '>=', now())
            ->whereHas('location', fn ($query) => $query->whereRaw('LOWER(suburb) = ?', [Str::lower(trim($suburb))]))
            ->with(['hero', 'location', 'categories', 'hostedFor'])
            ->orderBy('starts_at')
            ->get();
    }

    public function resolveSuburb(string $slug): ?string
    {
        return Location::query()
            ->whereNotNull('suburb')
            ->where('suburb', '!=', '')
            ->distinct()
            ->pluck('suburb')
            ->first(fn ($suburb): bool => Str::slug((string) $suburb) === Str::slug($slug));
    }

    /** @return \Illuminate\Support\Collection<int, string> */
    public function nearbySuburbs(string $suburb, int $limit = 8): \Illuminate\Support\Collection
    {
        $source = Location::query()->whereRaw('LOWER(suburb) = ?', [Str::lower(trim($suburb))])->first();
        if (! $source instanceof Location) {
            return collect();
        }

        return Location::query()
            ->whereNotNull('suburb')
            ->whereRaw('LOWER(suburb) != ?', [Str::lower(trim($suburb))])
            ->when(trim((string) $source->state) !== '', fn ($query) => $query->where('state', $source->state))
            ->get()
            ->unique(fn (Location $location): string => Str::lower(trim((string) $location->suburb)))
            ->sortBy(function (Location $location) use ($source): float {
                if (! is_numeric($source->latitude) || ! is_numeric($source->longitude) || ! is_numeric($location->latitude) || ! is_numeric($location->longitude)) {
                    return 999999;
                }
                $latDelta = deg2rad((float) $location->latitude - (float) $source->latitude);
                $lonDelta = deg2rad((float) $location->longitude - (float) $source->longitude);
                $a = sin($latDelta / 2) ** 2 + cos(deg2rad((float) $source->latitude)) * cos(deg2rad((float) $location->latitude)) * sin($lonDelta / 2) ** 2;

                return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
            })
            ->pluck('suburb')
            ->take($limit)
            ->values();
    }

    /** @return array<int, string> */
    private function organisationFamilyIds(Workshop $source): array
    {
        $organisation = $source->hostedFor;
        if (! $organisation instanceof Organisation) {
            return [];
        }

        while ($organisation->parent instanceof Organisation) {
            $organisation = $organisation->parent;
            $organisation->loadMissing('parent');
        }

        return array_values(array_unique([(string) $organisation->id, ...$organisation->descendantIds()]));
    }

    /** @param array<int, string> $organisationIds @param array<int, int> $categoryIds */
    private function score(Workshop $source, Workshop $candidate, array $organisationIds, array $categoryIds): array
    {
        $sameOrganisation = $source->hosted_for_organisation_id !== null
            && (string) $source->hosted_for_organisation_id === (string) $candidate->hosted_for_organisation_id;
        $sameOrganisationFamily = $candidate->hosted_for_organisation_id !== null
            && in_array((string) $candidate->hosted_for_organisation_id, $organisationIds, true);
        $sameSuburb = trim((string) $source->location?->suburb) !== ''
            && Str::lower(trim((string) $source->location?->suburb)) === Str::lower(trim((string) $candidate->location?->suburb));
        $distance = $this->distanceKm($source, $candidate);
        $sharedCategories = $candidate->categories->pluck('id')->intersect($categoryIds)->count();

        return [
            $sameOrganisation ? 0 : ($sameOrganisationFamily ? 1 : ($sameSuburb ? 2 : ($distance !== null ? 3 : 4))),
            $distance ?? 999999,
            -$sharedCategories,
            $candidate->starts_at?->timestamp ?? PHP_INT_MAX,
        ];
    }

    private function distanceKm(Workshop $source, Workshop $candidate): ?float
    {
        $lat1 = $source->location?->latitude;
        $lon1 = $source->location?->longitude;
        $lat2 = $candidate->location?->latitude;
        $lon2 = $candidate->location?->longitude;
        if (! is_numeric($lat1) || ! is_numeric($lon1) || ! is_numeric($lat2) || ! is_numeric($lon2)) {
            return null;
        }

        $latDelta = deg2rad((float) $lat2 - (float) $lat1);
        $lonDelta = deg2rad((float) $lon2 - (float) $lon1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad((float) $lat1)) * cos(deg2rad((float) $lat2)) * sin($lonDelta / 2) ** 2;

        return 6371 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
