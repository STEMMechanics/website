<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class WorkshopCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon_class',
        'hide_in_footer',
    ];

    protected $casts = [
        'hide_in_footer' => 'boolean',
    ];

    /**
     * @return BelongsToMany<Workshop, $this>
     */
    public function workshops(): BelongsToMany
    {
        return $this->belongsToMany(Workshop::class, 'workshop_category_workshop')->withTimestamps();
    }

    public function iconClass(): string
    {
        $iconClass = trim((string) ($this->icon_class ?? ''));

        return $iconClass !== '' ? $iconClass : 'fa-solid fa-tag';
    }

    public static function uniqueSlug(string $seed, ?int $ignoreId = null): string
    {
        $base = Str::slug(trim($seed));
        if ($base === '') {
            $base = 'category';
        }

        $candidate = $base;
        $suffix = 2;

        while (self::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $candidate)
            ->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix += 1;
        }

        return $candidate;
    }
}
