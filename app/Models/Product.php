<?php

namespace App\Models;

use App\Support\ShopShippingSettings;
use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasFiles;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const PRODUCT_TYPE_DIGITAL = 'digital';

    public const PRODUCT_TYPE_PHYSICAL = 'physical';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ACTIVE,
        self::STATUS_ARCHIVED,
    ];

    public const PRODUCT_TYPES = [
        self::PRODUCT_TYPE_DIGITAL,
        self::PRODUCT_TYPE_PHYSICAL,
    ];

    protected $fillable = [
        'slug',
        'title',
        'category',
        'sku',
        'status',
        'product_type',
        'short_description',
        'description',
        'hero_media_name',
        'price',
        'compare_at_price',
        'shipping_rate',
        'tax_rate',
        'inventory_quantity',
        'shipping_units',
        'min_satchel_rank',
        'weight_grams',
        'box_only',
        'length_cm',
        'width_cm',
        'height_cm',
        'is_featured',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'shipping_rate' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'inventory_quantity' => 'integer',
        'shipping_units' => 'decimal:2',
        'min_satchel_rank' => 'integer',
        'weight_grams' => 'integer',
        'box_only' => 'boolean',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $product): void {
            $product->slug = self::uniqueSlug(
                trim((string) ($product->slug ?: $product->title)),
                $product->exists ? (int) $product->id : null
            );
        });
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort_order')->orderBy('name')->orderBy('id');
    }

    /**
     * @return HasMany<StoreOrderItem, $this>
     */
    public function storeOrderItems(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function isActive(): bool
    {
        return (string) $this->status === self::STATUS_ACTIVE;
    }

    public function isDigital(): bool
    {
        return (string) $this->product_type === self::PRODUCT_TYPE_DIGITAL;
    }

    public function isPhysical(): bool
    {
        return (string) $this->product_type === self::PRODUCT_TYPE_PHYSICAL;
    }

    public function galleryMedia()
    {
        return $this->files('gallery');
    }

    public function downloadMedia()
    {
        return $this->files('downloads');
    }

    public function primaryImageUrl(): string
    {
        if ($this->hero instanceof Media) {
            return (string) $this->hero->thumbnail;
        }

        return asset('/thumbnails/unknown.webp');
    }

    public function hasVariants(): bool
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->contains(fn (ProductVariant $variant) => $variant->is_active);
        }

        return $this->variants()->where('is_active', true)->exists();
    }

    /**
     * @return Collection<int, ProductVariant>
     */
    public function purchasableVariants(): Collection
    {
        if ($this->relationLoaded('variants')) {
            return $this->variants->filter(fn (ProductVariant $variant) => $variant->is_active)->values();
        }

        return $this->variants()->active()->get();
    }

    public function variantById(?int $variantId): ?ProductVariant
    {
        if (! $variantId) {
            return null;
        }

        if ($this->relationLoaded('variants')) {
            return $this->variants
                ->first(fn (ProductVariant $variant) => $variant->id === $variantId && $variant->is_active);
        }

        return $this->variants()->active()->find($variantId);
    }

    public function defaultPurchasableVariant(): ?ProductVariant
    {
        return $this->purchasableVariants()->first();
    }

    public function priceForVariant(?ProductVariant $variant = null): float
    {
        return $variant instanceof ProductVariant
            ? $variant->effectivePrice()
            : round((float) $this->price, 2);
    }

    public function compareAtPriceForVariant(?ProductVariant $variant = null): ?float
    {
        if ($variant instanceof ProductVariant) {
            return $variant->effectiveCompareAtPrice();
        }

        return $this->compare_at_price !== null ? round((float) $this->compare_at_price, 2) : null;
    }

    public function shippingUnitsForVariant(?ProductVariant $variant = null): float
    {
        return round(max(0, (float) $this->shipping_units), 2);
    }

    public function minSatchelRankForVariant(?ProductVariant $variant = null): int
    {
        return max(1, (int) $this->min_satchel_rank);
    }

    public function boxOnlyForVariant(?ProductVariant $variant = null): bool
    {
        return (bool) $this->box_only;
    }

    public function weightGramsForVariant(?ProductVariant $variant = null): ?int
    {
        return $variant instanceof ProductVariant
            ? $variant->effectiveWeightGrams()
            : ($this->weight_grams !== null ? (int) $this->weight_grams : null);
    }

    public function tracksInventory(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $variant->tracksInventory();
        }

        return $this->inventory_quantity !== null;
    }

    public function availableInventory(?ProductVariant $variant = null): ?int
    {
        if ($variant instanceof ProductVariant) {
            return $variant->availableInventory();
        }

        return $this->inventory_quantity !== null ? max(0, (int) $this->inventory_quantity) : null;
    }

    public function isInStock(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $variant->is_active && $variant->isInStock();
        }

        if ($this->hasVariants()) {
            return $this->purchasableVariants()->contains(fn (ProductVariant $item) => $item->isInStock());
        }

        return $this->availableInventory() === null || $this->availableInventory() > 0;
    }

    public function shippingModeLabel(): string
    {
        if ($this->isDigital()) {
            return 'Instant digital access after payment';
        }

        if ($this->boxOnlyForVariant()) {
            return 'Requires boxed shipping';
        }

        $satchel = $this->satchelLabelForRank($this->minSatchelRankForVariant());
        if ($satchel === null) {
            return 'Satchel shipping';
        }

        return 'Fits '.$satchel.' satchel or larger';
    }

    public function priceLabel(?ProductVariant $variant = null): string
    {
        return '$'.number_format($this->priceForVariant($variant), 2);
    }

    public function priceRangeLabel(): string
    {
        $variants = $this->purchasableVariants();
        if ($variants->isEmpty()) {
            return $this->priceLabel();
        }

        $prices = $variants->map(fn (ProductVariant $variant) => $variant->effectivePrice())->unique()->sort()->values();
        if ($prices->count() <= 1) {
            return '$'.number_format((float) $prices->first(), 2);
        }

        return 'From $'.number_format((float) $prices->first(), 2);
    }

    public static function satchelOptions(): Collection
    {
        return ShopShippingSettings::satchels()
            ->filter(fn (array $satchel): bool => $satchel['active'] !== false)
            ->sortBy('rank')
            ->values();
    }

    public static function satchelLabelForRank(?int $rank): ?string
    {
        if ($rank === null) {
            return null;
        }

        $satchel = static::satchelOptions()->first(fn ($item) => (int) ($item['rank'] ?? 0) === (int) $rank);

        return is_array($satchel) ? (string) ($satchel['label'] ?? null) : null;
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_ARCHIVED => 'Archived',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function productTypeLabel(string $type): string
    {
        return match ($type) {
            self::PRODUCT_TYPE_DIGITAL => 'Digital Download',
            self::PRODUCT_TYPE_PHYSICAL => 'Physical Item',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    private static function uniqueSlug(string $source, ?int $ignoreId = null): string
    {
        $base = Str::slug($source);
        if ($base === '') {
            $base = 'product';
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
