<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

class CustomPage extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'title',
        'path',
        'aliases',
        'content',
        'hero_media_name',
        'show_mast',
        'seo_title',
        'seo_description',
        'seo_noindex',
        'is_published',
        'user_id',
    ];

    protected $casts = [
        'aliases' => 'array',
        'show_mast' => 'boolean',
        'seo_noindex' => 'boolean',
        'is_published' => 'boolean',
    ];

    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function resolvedMastBackTitle(): ?string
    {
        return $this->dynamicParent()?->title;
    }

    public function resolvedMastBackUrl(): ?string
    {
        return $this->dynamicParent()?->path;
    }

    public function dynamicParent(): ?self
    {
        $parentPath = $this->dynamicParentPath();
        if ($parentPath === null) {
            return null;
        }

        if ($this->relationLoaded('dynamicParentPage')) {
            $loadedParent = $this->getRelation('dynamicParentPage');

            return $loadedParent instanceof self ? $loadedParent : null;
        }

        $parent = static::query()
            ->published()
            ->where('path', $parentPath)
            ->first();

        $this->setRelation('dynamicParentPage', $parent);

        return $parent;
    }

    public function dynamicParentPath(): ?string
    {
        $segments = collect(explode('/', trim((string) $this->path, '/')))
            ->filter(fn (string $segment) => $segment !== '')
            ->values();

        if ($segments->count() <= 1) {
            return null;
        }

        /** @var Collection<int, string> $parentSegments */
        $parentSegments = $segments->slice(0, -1)->values();

        return '/'.$parentSegments->implode('/');
    }

    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    public static function normalizePath(string $path): string
    {
        $normalized = '/'.trim($path, '/');

        return $normalized === '/' ? '/' : rtrim($normalized, '/');
    }

    public static function normalizeAliases(array $aliases, string $canonicalPath = '', ?string $ignoreId = null): array
    {
        $canonical = static::normalizePath($canonicalPath);

        return collect($aliases)
            ->map(fn ($alias) => static::normalizePath((string) $alias))
            ->filter(fn (string $alias) => $alias !== '/' && $alias !== $canonical)
            ->unique()
            ->values()
            ->all();
    }
}
