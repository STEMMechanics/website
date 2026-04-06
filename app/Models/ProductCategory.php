<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class ProductCategory extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'icon_class',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_category_product')->withPivot('sort_order')->withTimestamps();
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

    public static function resolveFromLabel(string $label): ?self
    {
        $name = trim($label);
        if ($name === '') {
            return null;
        }

        $slug = self::uniqueSlug($name);

        $existing = self::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->orWhereRaw('LOWER(slug) = ?', [mb_strtolower(Str::slug($name) ?: $slug)])
            ->orderBy('id')
            ->first();

        if ($existing instanceof self) {
            return $existing;
        }

        return self::query()->create([
            'name' => $name,
            'slug' => $slug,
            'icon_class' => 'fa-solid fa-tag',
            'sort_order' => ((int) (self::query()->max('sort_order') ?? 0)) + 1,
        ]);
    }
}
