<?php

namespace App\Services;

use App\Jobs\Media\GeneratePerceptualHash;
use App\Models\Media;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class MediaDuplicateService
{
    public const ATTENTION_CACHE_KEY = 'media:duplicate-attention-count';

    public const SIMILAR_PAIRS_CACHE_KEY = 'media:similar-image-pairs:v2';

    private const LEGACY_SIMILAR_PAIRS_CACHE_KEY = 'media:similar-image-pairs';

    public const MERGEABLE_METADATA_FIELDS = [
        'title',
        'user_id',
        'visibility',
        'tags',
        'caption',
        'consent_notes',
        'photographed_at',
        'created_at',
        'status',
        'password',
    ];

    private const SIMILARITY_DISTANCE_THRESHOLD = 8;

    /**
     * @return Collection<int, array{hash: string, storage_disk: string, media: EloquentCollection<int, Media>}>
     */
    public function groups(): Collection
    {
        $groups = DB::table('media')
            ->select('hash')
            ->selectRaw("COALESCE(storage_disk, 'media') as normalized_storage_disk")
            ->whereNotNull('hash')
            ->where('hash', '!=', '')
            ->groupBy('hash', DB::raw("COALESCE(storage_disk, 'media')"))
            ->havingRaw('COUNT(*) > 1')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        if ($groups->isEmpty()) {
            return collect();
        }

        /** @var Collection<string, EloquentCollection<int, Media>> $mediaByGroup */
        $mediaByGroup = Media::query()
            ->with('user')
            ->withCount('workshops')
            ->whereIn('hash', $groups->pluck('hash')->unique()->all())
            ->oldest()
            ->get()
            ->groupBy(function (Media $media): string {
                $storageDisk = $media->getRawOriginal('storage_disk');

                return (string) $media->hash.'|'.($storageDisk === null ? 'media' : (string) $storageDisk);
            });

        return $groups->map(function (object $group) use ($mediaByGroup): array {
            $hash = (string) ($group->hash ?? '');
            $storageDisk = (string) ($group->normalized_storage_disk ?? 'media');
            /** @var EloquentCollection<int, Media> $media */
            $media = $mediaByGroup->get($hash.'|'.$storageDisk, new EloquentCollection());

            return [
                'hash' => $hash,
                'storage_disk' => $storageDisk,
                'media' => $media,
            ];
        })->filter(fn (array $group): bool => $group['media']->count() > 1)->values();
    }

    public function attentionCount(ImagePerceptualHash $hasher): int
    {
        return (int) Cache::remember(self::ATTENTION_CACHE_KEY, now()->addMinutes(10), function () use ($hasher): int {
            $matches = array_filter(
                $this->cachedSimilarPairData($hasher),
                fn (array $pair): bool => ! (bool) ($pair['ignored'] ?? false),
            );

            return $this->groups()->count() + count($matches);
        });
    }

    public function queueUnscannedImageHashes(): int
    {
        $queued = 0;

        Media::query()
            ->where('mime_type', 'like', 'image/%')
            ->whereNull('perceptual_hash_scanned_at')
            ->orderBy('name')
            ->chunkById(250, function ($mediaItems) use (&$queued): void {
                foreach ($mediaItems as $media) {
                    dispatch(new GeneratePerceptualHash((string) $media->name))->onQueue('media');
                    $queued++;
                }
            }, 'name');

        return $queued;
    }

    public function clearAttentionCount(): void
    {
        Cache::forget(self::ATTENTION_CACHE_KEY);
        Cache::forget(self::SIMILAR_PAIRS_CACHE_KEY);
        Cache::forget(self::LEGACY_SIMILAR_PAIRS_CACHE_KEY);
    }

    public function refreshSimilarPairs(ImagePerceptualHash $hasher): int
    {
        $pairs = $this->calculateSimilarPairs($hasher);
        $cachedPairs = $this->cacheableSimilarPairs($pairs);
        Cache::put(self::SIMILAR_PAIRS_CACHE_KEY, $cachedPairs, now()->addHours(36));
        Cache::forget(self::ATTENTION_CACHE_KEY);

        return $pairs->where('ignored', false)->count();
    }

    /**
     * @param  array<int, string>  $duplicateNames
     */
    public function merge(Media $keeper, array $duplicateNames, array $metadataSources = []): int
    {
        $duplicates = Media::query()
            ->whereIn('name', array_values(array_unique($duplicateNames)))
            ->where('name', '!=', $keeper->name)
            ->get();

        foreach ($duplicates as $duplicate) {
            if ((string) $duplicate->hash !== (string) $keeper->hash
                || $duplicate->storageDiskName() !== $keeper->storageDiskName()) {
                throw new InvalidArgumentException('Only exact duplicates stored on the same disk can be merged.');
            }
        }

        return $this->mergeRecords($keeper, $duplicates, true, $metadataSources);
    }

    public function mergeSimilar(Media $keeper, Media $duplicate, ImagePerceptualHash $hasher, array $metadataSources = []): void
    {
        $distance = $hasher->distance((string) $keeper->perceptual_hash, (string) $duplicate->perceptual_hash);
        if ($distance > self::SIMILARITY_DISTANCE_THRESHOLD || (string) $keeper->hash === (string) $duplicate->hash) {
            throw new InvalidArgumentException('These media items are not a current similar-image match.');
        }

        $this->mergeRecords($keeper, collect([$duplicate]), false, $metadataSources);
    }

    /**
     * @param  Media|array<int, string>  $duplicates
     */
    public function mergeManual(Media $keeper, Media|array $duplicates, array $metadataSources = []): int
    {
        $duplicateRecords = $duplicates instanceof Media
            ? collect([$duplicates])
            : Media::query()->whereIn('name', array_values(array_unique($duplicates)))->get();

        if ($duplicateRecords->isEmpty()
            || $duplicateRecords->contains(fn (Media $duplicate): bool => $keeper->name === $duplicate->name
                || ! str_starts_with((string) $duplicate->mime_type, 'image/'))
            || ! str_starts_with((string) $keeper->mime_type, 'image/')) {
            throw new InvalidArgumentException('Manual duplicate review is available for two or more different images only.');
        }

        if (is_array($duplicates) && $duplicateRecords->count() !== count(array_unique($duplicates))) {
            throw new InvalidArgumentException('One or more selected images could not be found.');
        }

        return $this->mergeRecords($keeper, $duplicateRecords, false, $metadataSources);
    }

    /**
     * @param  Collection<int, Media>  $duplicates
     */
    private function mergeRecords(Media $keeper, Collection $duplicates, bool $normalizeStorageDisk, array $metadataSources = []): int
    {
        $records = collect([$keeper])->concat($duplicates)->keyBy(fn (Media $media): string => (string) $media->name);
        $invalidFields = array_diff(array_keys($metadataSources), self::MERGEABLE_METADATA_FIELDS);
        if ($invalidFields !== []) {
            throw new InvalidArgumentException('One or more selected metadata fields cannot be merged.');
        }

        foreach ($metadataSources as $sourceName) {
            if (! is_string($sourceName) || ! $records->has($sourceName)) {
                throw new InvalidArgumentException('Metadata can only be selected from the records being merged.');
            }
        }

        DB::transaction(function () use ($keeper, $duplicates, $normalizeStorageDisk, $metadataSources, $records): void {
            foreach ($metadataSources as $field => $sourceName) {
                $keeper->setAttribute($field, $records->get($sourceName)?->getRawOriginal($field));
            }
            if ($metadataSources !== []) {
                $keeper->save();
            }

            foreach ($duplicates as $duplicate) {
                $this->movePivotReferences((string) $duplicate->name, (string) $keeper->name);
                $this->moveDirectReferences((string) $duplicate->name, (string) $keeper->name);
                $this->moveContentReferences((string) $duplicate->name, (string) $keeper->name);
                if ($normalizeStorageDisk) {
                    $duplicate->storage_disk = $keeper->getRawOriginal('storage_disk');
                    $duplicate->save();
                }
                $duplicate->delete();
            }
        });

        return $duplicates->count();
    }

    /**
     * @return Collection<int, array{first: Media, second: Media, distance: int, similarity: float, ignored: bool}>
     */
    public function similarPairs(ImagePerceptualHash $hasher, bool $ignoredOnly = false): Collection
    {
        $selectedPairs = collect($this->cachedSimilarPairData($hasher))
            ->filter(fn ($pair): bool => is_array($pair)
                && is_string($pair['first_name'] ?? null)
                && is_string($pair['second_name'] ?? null)
                && (bool) ($pair['ignored'] ?? false) === $ignoredOnly)
            ->values();
        $mediaNames = $selectedPairs
            ->flatMap(fn (array $pair): array => [$pair['first_name'], $pair['second_name']])
            ->unique()
            ->values();
        if ($mediaNames->isEmpty()) {
            return collect();
        }

        $mediaByName = collect();
        foreach ($mediaNames->chunk(500) as $nameChunk) {
            $mediaByName = $mediaByName->merge(
                Media::query()->with('user')->whereIn('name', $nameChunk)->get(),
            );
        }
        $mediaByName = $mediaByName->keyBy(fn (Media $media): string => (string) $media->name);

        return $selectedPairs->map(function (array $pair) use ($mediaByName): ?array {
            $first = $mediaByName->get($pair['first_name']);
            $second = $mediaByName->get($pair['second_name']);
            if (! $first instanceof Media || ! $second instanceof Media) {
                return null;
            }

            return [
                'first' => $first,
                'second' => $second,
                'distance' => (int) $pair['distance'],
                'similarity' => (float) $pair['similarity'],
                'ignored' => (bool) $pair['ignored'],
            ];
        })->filter()->values();
    }

    /**
     * @return array<int, array{first_name: string, second_name: string, distance: int, similarity: float, ignored: bool}>
     */
    private function cachedSimilarPairData(ImagePerceptualHash $hasher): array
    {
        Cache::forget(self::LEGACY_SIMILAR_PAIRS_CACHE_KEY);
        $pairs = Cache::remember(
            self::SIMILAR_PAIRS_CACHE_KEY,
            now()->addHours(36),
            fn (): array => $this->cacheableSimilarPairs($this->calculateSimilarPairs($hasher)),
        );
        if (! is_array($pairs)) {
            $pairs = $this->cacheableSimilarPairs($this->calculateSimilarPairs($hasher));
            Cache::put(self::SIMILAR_PAIRS_CACHE_KEY, $pairs, now()->addHours(36));
        }

        return array_values(array_filter($pairs, fn ($pair): bool => is_array($pair)
            && is_string($pair['first_name'] ?? null)
            && is_string($pair['second_name'] ?? null)
            && isset($pair['distance'], $pair['similarity'])));
    }

    /**
     * @return Collection<int, array{first: Media, second: Media, distance: int, similarity: float, ignored: bool}>
     */
    private function calculateSimilarPairs(ImagePerceptualHash $hasher): Collection
    {
        $media = Media::query()
            ->whereNotNull('perceptual_hash')
            ->where('perceptual_hash', '!=', '')
            ->where('mime_type', 'like', 'image/%')
            ->orderBy('name')
            ->get()
            ->values();
        $ignoredPairs = DB::table('media_similarity_ignores')
            ->get(['media_name_a', 'media_name_b'])
            ->mapWithKeys(fn (object $row): array => [($row->media_name_a ?? '').'|'.($row->media_name_b ?? '') => true]);
        $pairs = collect();

        for ($firstIndex = 0; $firstIndex < $media->count(); $firstIndex++) {
            $first = $media[$firstIndex];
            for ($secondIndex = $firstIndex + 1; $secondIndex < $media->count(); $secondIndex++) {
                $second = $media[$secondIndex];
                if ((string) $first->hash === (string) $second->hash) {
                    continue;
                }

                $distance = $hasher->distance((string) $first->perceptual_hash, (string) $second->perceptual_hash);
                if ($distance > self::SIMILARITY_DISTANCE_THRESHOLD) {
                    continue;
                }

                [$nameA, $nameB] = $this->normalizedPair((string) $first->name, (string) $second->name);
                $ignored = $ignoredPairs->has($nameA.'|'.$nameB);

                $pairs->push([
                    'first' => $first,
                    'second' => $second,
                    'distance' => $distance,
                    'similarity' => round((64 - $distance) / 64 * 100, 1),
                    'ignored' => $ignored,
                ]);
            }
        }

        return $pairs->sortBy('distance')->values();
    }

    /**
     * @param  Collection<int, array{first: Media, second: Media, distance: int, similarity: float, ignored: bool}>  $pairs
     * @return array<int, array{first_name: string, second_name: string, distance: int, similarity: float, ignored: bool}>
     */
    private function cacheableSimilarPairs(Collection $pairs): array
    {
        return $pairs->map(fn (array $pair): array => [
            'first_name' => (string) $pair['first']->name,
            'second_name' => (string) $pair['second']->name,
            'distance' => (int) $pair['distance'],
            'similarity' => (float) $pair['similarity'],
            'ignored' => (bool) $pair['ignored'],
        ])->all();
    }

    public function ignoreSimilarPair(Media $first, Media $second, ImagePerceptualHash $hasher): void
    {
        [$nameA, $nameB] = $this->normalizedPair((string) $first->name, (string) $second->name);
        if ($nameA === $nameB) {
            throw new InvalidArgumentException('A media item cannot be ignored as similar to itself.');
        }
        if ((string) $first->hash === (string) $second->hash
            || $hasher->distance((string) $first->perceptual_hash, (string) $second->perceptual_hash) > self::SIMILARITY_DISTANCE_THRESHOLD) {
            throw new InvalidArgumentException('These media items are not a current similar-image match.');
        }

        DB::table('media_similarity_ignores')->insertOrIgnore([
            'media_name_a' => $nameA,
            'media_name_b' => $nameB,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->updateCachedSimilarPairIgnoreStates([['first' => $nameA, 'second' => $nameB]], true);
    }

    public function restoreSimilarPair(Media $first, Media $second): void
    {
        [$nameA, $nameB] = $this->normalizedPair((string) $first->name, (string) $second->name);
        DB::table('media_similarity_ignores')
            ->where('media_name_a', $nameA)
            ->where('media_name_b', $nameB)
            ->delete();
        $this->updateCachedSimilarPairIgnoreStates([['first' => $nameA, 'second' => $nameB]], false);
    }

    /**
     * @param  array<int, array{first: string, second: string}>  $pairs
     */
    private function updateCachedSimilarPairIgnoreStates(array $pairs, bool $ignored): void
    {
        $ignoreKeys = [];
        foreach ($pairs as $pair) {
            [$nameA, $nameB] = $this->normalizedPair($pair['first'], $pair['second']);
            $ignoreKeys[$nameA.'|'.$nameB] = true;
        }

        $cachedPairs = Cache::get(self::SIMILAR_PAIRS_CACHE_KEY);
        if (is_array($cachedPairs)) {
            foreach ($cachedPairs as &$pair) {
                if (! is_array($pair) || ! is_string($pair['first_name'] ?? null) || ! is_string($pair['second_name'] ?? null)) {
                    continue;
                }
                [$nameA, $nameB] = $this->normalizedPair($pair['first_name'], $pair['second_name']);
                if (isset($ignoreKeys[$nameA.'|'.$nameB])) {
                    $pair['ignored'] = $ignored;
                }
            }
            unset($pair);
            Cache::put(self::SIMILAR_PAIRS_CACHE_KEY, $cachedPairs, now()->addHours(36));
        }

        Cache::forget(self::ATTENTION_CACHE_KEY);
    }

    /**
     * @return array{string, string}
     */
    private function normalizedPair(string $first, string $second): array
    {
        return strcmp($first, $second) <= 0 ? [$first, $second] : [$second, $first];
    }

    private function movePivotReferences(string $from, string $to): void
    {
        if (! Schema::hasTable('mediables')) {
            return;
        }

        foreach (DB::table('mediables')->where('media_name', $from)->get() as $row) {
            $existing = DB::table('mediables')
                ->where('media_name', $to)
                ->where('mediable_id', $row->mediable_id)
                ->where('mediable_type', $row->mediable_type)
                ->where(function ($query) use ($row): void {
                    $row->collection === null
                        ? $query->whereNull('collection')
                        : $query->where('collection', $row->collection);
                })
                ->exists();

            if ($existing) {
                DB::table('mediables')->where('id', $row->id)->delete();
            } else {
                DB::table('mediables')->where('id', $row->id)->update(['media_name' => $to]);
            }
        }
    }

    private function moveDirectReferences(string $from, string $to): void
    {
        foreach ([
            ['workshops', 'hero_media_name'],
            ['posts', 'hero_media_name'],
            ['custom_pages', 'hero_media_name'],
            ['products', 'hero_media_name'],
            ['class_sessions', 'hero_media_name'],
            ['users', 'avatar_media_name'],
            ['store_order_item_downloads', 'media_name'],
        ] as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::table($table)->where($column, $from)->update([$column => $to]);
            }
        }
    }

    private function moveContentReferences(string $from, string $to): void
    {
        foreach ([
            ['workshops', 'content'],
            ['posts', 'content'],
            ['custom_pages', 'content'],
        ] as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            foreach (DB::table($table)->where($column, 'like', '%'.$from.'%')->get(['id', $column]) as $row) {
                $content = (string) ($row->{$column} ?? '');
                DB::table($table)->where('id', $row->id)->update([$column => str_replace(
                    ['/media/download/'.$from, '/media/download/'.rawurlencode($from)],
                    ['/media/download/'.$to, '/media/download/'.rawurlencode($to)],
                    $content
                )]);
            }
        }
    }
}
