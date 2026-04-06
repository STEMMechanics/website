<?php

namespace App\Models;

use App\Support\ShopShippingSettings;
use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Product extends Model
{
    use HasFactory, HasFiles;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_ARCHIVED = 'archived';

    public const PRODUCT_TYPE_DIGITAL = 'digital';

    public const PRODUCT_TYPE_PHYSICAL = 'physical';

    public const BACKORDER_SHIPPING_ESTIMATE_STATIC = 'static';

    public const BACKORDER_SHIPPING_ESTIMATE_DYNAMIC = 'dynamic';

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
        'is_preorder',
        'preorder_shipping_estimate',
        'allow_backorder',
        'backorder_shipping_estimate',
        'backorder_shipping_estimate_type',
        'backorder_shipping_offset_days',
        'short_description',
        'description',
        'base_variant_name',
        'base_variant_description',
        'private_notes',
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
        'low_stock_threshold',
        'low_stock_alert_sent_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'shipping_rate' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'is_preorder' => 'boolean',
        'preorder_shipping_estimate' => 'date',
        'allow_backorder' => 'boolean',
        'backorder_shipping_estimate' => 'date',
        'backorder_shipping_offset_days' => 'integer',
        'inventory_quantity' => 'integer',
        'shipping_units' => 'decimal:3',
        'min_satchel_rank' => 'integer',
        'weight_grams' => 'integer',
        'box_only' => 'boolean',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'is_featured' => 'boolean',
        'sort_order' => 'integer',
        'low_stock_threshold' => 'integer',
        'low_stock_alert_sent_at' => 'datetime',
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

    public function isPreorder(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $variant->isPreorder();
        }

        return false;
    }

    public function allowsBackorder(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $variant->allowsBackorder();
        }

        return (bool) ($this->allow_backorder || $this->is_preorder);
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
        if ($variant instanceof ProductVariant) {
            return $variant->effectiveShippingUnits();
        }

        return round(max(0, (float) $this->shipping_units), 3);
    }

    public function defaultVariantName(): string
    {
        return $this->isDigital() ? 'Home' : 'Base';
    }

    public function baseOptionName(): string
    {
        $name = trim((string) ($this->base_variant_name ?? ''));

        return $name !== '' ? $name : $this->defaultVariantName();
    }

    public function baseOptionDescription(): ?string
    {
        $description = trim((string) ($this->base_variant_description ?? ''));

        return $description !== '' ? $description : null;
    }

    public function hasOptionChoices(): bool
    {
        return $this->hasVariants();
    }

    public function optionChoiceCount(): int
    {
        return $this->hasOptionChoices() ? $this->purchasableVariants()->count() + 1 : 0;
    }

    public function variantDisplayName(?ProductVariant $variant = null): ?string
    {
        if (! $variant instanceof ProductVariant) {
            return $this->hasOptionChoices() ? $this->baseOptionName() : null;
        }

        $name = trim((string) $variant->name);

        return $name !== '' ? $name : 'Variant';
    }

    public function displayTitle(?ProductVariant $variant = null): string
    {
        $variantLabel = $this->variantDisplayName($variant);
        if ($variantLabel === null) {
            return (string) $this->title;
        }

        return (string) $this->title.' - '.$variantLabel;
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
            if ($this->isPhysical()) {
                return true;
            }

            return $variant->tracksInventory();
        }

        return $this->inventory_quantity !== null;
    }

    public function availableInventory(?ProductVariant $variant = null): ?int
    {
        if ($variant instanceof ProductVariant) {
            if ($this->isPhysical() && $variant->inventory_quantity === null) {
                return 0;
            }

            return $variant->availableInventory();
        }

        return $this->inventory_quantity !== null ? max(0, (int) $this->inventory_quantity) : null;
    }

    public function availableInventoryForPurchase(?ProductVariant $variant = null): ?int
    {
        if ($this->isPreorder($variant) || $this->allowsBackorder($variant)) {
            return null;
        }

        return $this->availableInventory($variant);
    }

    public function tracksInventoryForPurchase(?ProductVariant $variant = null): bool
    {
        if ($this->isPreorder($variant) || $this->allowsBackorder($variant)) {
            return false;
        }

        return $this->tracksInventory($variant);
    }

    public function isSelectionInStock(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            if (! $variant->is_active) {
                return false;
            }

            $available = $this->availableInventory($variant);

            return $available === null || $available > 0;
        }

        return $this->availableInventory() === null || $this->availableInventory() > 0;
    }

    public function isSelectionPurchasable(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $variant->is_active && ($this->isPreorder($variant) || $this->allowsBackorder($variant) || $this->isSelectionInStock($variant));
        }

        return $this->isPreorder() || $this->allowsBackorder() || $this->isSelectionInStock();
    }

    public function isInStock(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $this->isSelectionInStock($variant);
        }

        if ($this->hasOptionChoices()) {
            if ($this->isSelectionInStock()) {
                return true;
            }

            return $this->purchasableVariants()->contains(fn (ProductVariant $item) => $this->isSelectionInStock($item));
        }

        return $this->isSelectionInStock();
    }

    public function isPurchasable(?ProductVariant $variant = null): bool
    {
        if ($variant instanceof ProductVariant) {
            return $this->isSelectionPurchasable($variant);
        }

        if ($this->hasOptionChoices()) {
            if ($this->isSelectionPurchasable()) {
                return true;
            }

            return $this->purchasableVariants()->contains(
                fn (ProductVariant $item) => $this->isSelectionPurchasable($item)
            );
        }

        return $this->isSelectionPurchasable();
    }

    public function preorderShippingEstimateLabel(string $format = 'F jS', ?ProductVariant $variant = null): ?string
    {
        if ($variant instanceof ProductVariant) {
            return $variant->preorderShippingEstimateLabel($format);
        }

        if (! $this->preorder_shipping_estimate instanceof Carbon) {
            return null;
        }

        return $this->preorder_shipping_estimate->format($format);
    }

    public function backorderShippingEstimate(?ProductVariant $variant = null): ?Carbon
    {
        if ($variant instanceof ProductVariant) {
            return $variant->backorderShippingEstimate();
        }

        $mode = $this->backorder_shipping_estimate_type;
        $offsetDays = $this->backorder_shipping_offset_days;

        if (($mode === self::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC || ($mode === null && $offsetDays !== null)) && $offsetDays !== null) {
            return Carbon::today()->addDays(max(0, (int) $offsetDays));
        }

        if ($this->backorder_shipping_estimate instanceof Carbon) {
            return $this->backorder_shipping_estimate;
        }

        if ($this->preorder_shipping_estimate instanceof Carbon) {
            return $this->preorder_shipping_estimate;
        }

        return null;
    }

    public function backorderShippingEstimateLabel(string $format = 'F jS', ?ProductVariant $variant = null): ?string
    {
        $estimate = $this->backorderShippingEstimate($variant);

        if (! $estimate instanceof Carbon) {
            return null;
        }

        return $estimate->format($format);
    }

    public function shippingModeLabel(?ProductVariant $variant = null): string
    {
        if ($this->isDigital()) {
            return 'Instant digital access after payment';
        }

        if ($this->boxOnlyForVariant($variant)) {
            return 'Requires rigid parcel shipping';
        }

        $satchel = $this->satchelLabelForRank($this->minSatchelRankForVariant($variant));
        if ($satchel === null) {
            return 'Package shipping';
        }

        return 'Fits '.$satchel.' package or larger';
    }

    public function priceLabel(?ProductVariant $variant = null): string
    {
        return self::priceAmountLabel($this->priceForVariant($variant));
    }

    public function priceRangeLabel(): string
    {
        $variants = $this->purchasableVariants();
        if ($variants->isEmpty()) {
            return $this->priceLabel();
        }

        $prices = $variants
            ->map(fn (ProductVariant $variant) => $variant->effectivePrice())
            ->prepend($this->priceForVariant())
            ->unique()
            ->sort()
            ->values();
        if ($prices->count() <= 1) {
            return self::priceAmountLabel((float) $prices->first());
        }

        return 'From '.self::priceAmountLabel((float) $prices->first());
    }

    public function trackedInventoryTotal(): ?int
    {
        $trackedInventories = [];

        if ($this->inventory_quantity !== null) {
            $trackedInventories[] = max(0, (int) $this->inventory_quantity);
        }

        $variants = $this->relationLoaded('variants')
            ? $this->variants
            : $this->variants()->get();

        foreach ($variants as $variant) {
            if (! $variant instanceof ProductVariant || ! $variant->is_active || $variant->inventory_quantity === null) {
                continue;
            }

            $trackedInventories[] = max(0, (int) $variant->inventory_quantity);
        }

        return $trackedInventories === [] ? null : array_sum($trackedInventories);
    }

    public function effectiveLowStockThreshold(): ?int
    {
        if (! $this->isPhysical()) {
            return null;
        }

        $threshold = $this->low_stock_threshold !== null ? (int) $this->low_stock_threshold : null;

        return $threshold !== null && $threshold > 0 ? $threshold : null;
    }

    public function isLowStock(?int $available = null): bool
    {
        $threshold = $this->effectiveLowStockThreshold();
        $resolvedAvailable = $available ?? $this->trackedInventoryTotal();

        return $threshold !== null
            && $resolvedAvailable !== null
            && $resolvedAvailable <= $threshold;
    }

    public static function priceAmountLabel(float $amount): string
    {
        $normalizedAmount = round(max(0, $amount), 2);

        if ($normalizedAmount <= 0.0001) {
            return 'Free';
        }

        return '$'.number_format($normalizedAmount, 2);
    }

    public static function satchelOptions(): Collection
    {
        if (Schema::hasTable('store_shipping_methods') && Schema::hasTable('store_shipping_method_packages')) {
            $methodQuery = StoreShippingMethod::query()
                ->where('is_active', true)
                ->where('is_pickup', false)
                ->with([
                    'packageOptions' => fn ($query) => $query
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->orderBy('id'),
                ])
                ->orderBy('sort_order')
                ->orderBy('id');

            if (Schema::hasColumn('store_shipping_methods', 'is_default')) {
                $methodQuery->orderByDesc('is_default');
            }

            $method = $methodQuery->first();

            if ($method instanceof StoreShippingMethod && $method->packageOptions->isNotEmpty()) {
                return $method->packageOptions
                    ->map(function (StoreShippingMethodPackage $package): array {
                        return [
                            'code' => (string) $package->code,
                            'label' => (string) $package->label,
                            'rank' => max(1, (int) $package->sort_order),
                            'capacity' => round((float) $package->capacity, 2),
                            'price' => round((float) $package->price, 2),
                            'active' => (bool) $package->is_active,
                        ];
                    })
                    ->sortBy('rank')
                    ->values();
            }
        }

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
