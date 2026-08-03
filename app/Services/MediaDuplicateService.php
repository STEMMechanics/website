<?php

namespace App\Services;

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

        return $groups->map(function (object $group): array {
            $hash = (string) ($group->hash ?? '');
            $storageDisk = (string) ($group->normalized_storage_disk ?? 'media');

            return [
                'hash' => $hash,
                'storage_disk' => $storageDisk,
                'media' => Media::query()
                    ->with('user')
                    ->where('hash', $hash)
                    ->where(function ($query) use ($storageDisk): void {
                        $query->where('storage_disk', $storageDisk);
                        if ($storageDisk === 'media') {
                            $query->orWhereNull('storage_disk');
                        }
                    })
                    ->oldest()
                    ->get(),
            ];
        })->filter(fn (array $group): bool => $group['media']->count() > 1)->values();
    }

    public function attentionCount(ImagePerceptualHash $hasher): int
    {
        return (int) Cache::remember(self::ATTENTION_CACHE_KEY, now()->addMinutes(10), function () use ($hasher): int {
            return $this->groups()->count() + $this->similarPairs($hasher)->count();
        });
    }

    public function clearAttentionCount(): void
    {
        Cache::forget(self::ATTENTION_CACHE_KEY);
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
            throw new InvalidArgumentException('These media items are not a current similar-image suggestion.');
        }

        $this->mergeRecords($keeper, collect([$duplicate]), false, $metadataSources);
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
        $media = Media::query()
            ->with('user')
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
                if ($ignored !== $ignoredOnly) {
                    continue;
                }

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

    public function ignoreSimilarPair(Media $first, Media $second, ImagePerceptualHash $hasher): void
    {
        [$nameA, $nameB] = $this->normalizedPair((string) $first->name, (string) $second->name);
        if ($nameA === $nameB) {
            throw new InvalidArgumentException('A media item cannot be ignored as similar to itself.');
        }
        if ((string) $first->hash === (string) $second->hash
            || $hasher->distance((string) $first->perceptual_hash, (string) $second->perceptual_hash) > self::SIMILARITY_DISTANCE_THRESHOLD) {
            throw new InvalidArgumentException('These media items are not a current similar-image suggestion.');
        }

        DB::table('media_similarity_ignores')->insertOrIgnore([
            'media_name_a' => $nameA,
            'media_name_b' => $nameB,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->clearAttentionCount();
    }

    public function restoreSimilarPair(Media $first, Media $second): void
    {
        [$nameA, $nameB] = $this->normalizedPair((string) $first->name, (string) $second->name);
        DB::table('media_similarity_ignores')
            ->where('media_name_a', $nameA)
            ->where('media_name_b', $nameB)
            ->delete();
        $this->clearAttentionCount();
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
