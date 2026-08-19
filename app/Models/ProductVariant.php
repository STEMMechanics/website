<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'description',
        'product_details',
        'sku',
        'price',
        'compare_at_price',
        'shipping_rate',
        'shipping_units',
        'inventory_quantity',
        'weight_grams',
        'is_preorder',
        'preorder_shipping_estimate',
        'allow_backorder',
        'backorder_shipping_estimate',
        'backorder_shipping_estimate_type',
        'backorder_shipping_offset_days',
        'length_mm',
        'width_mm',
        'height_mm',
        'is_active',
        'sort_order',
        'low_stock_threshold',
        'low_stock_alert_sent_at',
        'restocked_at',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'shipping_rate' => 'decimal:2',
        'shipping_units' => 'decimal:3',
        'inventory_quantity' => 'integer',
        'product_details' => 'array',
        'weight_grams' => 'integer',
        'is_preorder' => 'boolean',
        'preorder_shipping_estimate' => 'date',
        'allow_backorder' => 'boolean',
        'backorder_shipping_estimate' => 'date',
        'backorder_shipping_offset_days' => 'integer',
        'length_mm' => 'integer',
        'width_mm' => 'integer',
        'height_mm' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'low_stock_threshold' => 'integer',
        'low_stock_alert_sent_at' => 'datetime',
        'restocked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $variant): void {
            if ($variant->exists
                && $variant->isDirty('inventory_quantity')
                && $variant->getRawOriginal('inventory_quantity') !== null
                && (int) $variant->getRawOriginal('inventory_quantity') <= 0
                && $variant->inventory_quantity !== null
                && (int) $variant->inventory_quantity > 0) {
                $variant->restocked_at = now();
            }
        });

        static::saved(function (self $variant): void {
            if ($variant->wasChanged('restocked_at')) {
                $variant->product()->update(['restocked_at' => $variant->restocked_at]);
            }
        });
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
        return $query->where('is_active', true);
    }

    public function effectivePrice(): float
    {
        $product = $this->product;

        return round((float) ($this->price ?? ($product instanceof Product ? $product->price : 0)), 2);
    }

    public function effectiveCompareAtPrice(): ?float
    {
        $value = $this->compare_at_price;
        if ($value === null) {
            $value = $this->product?->compare_at_price;
        }

        return $value !== null ? round((float) $value, 2) : null;
    }

    public function effectiveShippingRate(): float
    {
        $product = $this->product;

        return round((float) ($this->shipping_rate ?? ($product instanceof Product ? $product->shipping_rate : 0)), 2);
    }

    public function effectiveShippingUnits(): float
    {
        $value = $this->product?->shipping_units;

        return round(max(0, (float) $value), 3);
    }

    public function displayName(): string
    {
        $name = trim((string) $this->name);
        if ($name !== '') {
            return $name;
        }

        return 'Variant';
    }

    public function effectiveWeightGrams(): ?int
    {
        $value = $this->weight_grams ?? $this->product?->weight_grams;

        return $value !== null ? (int) $value : null;
    }

    public function effectiveLengthMm(): ?int
    {
        $value = $this->length_mm ?? $this->product?->length_mm;

        return $value !== null ? (int) $value : null;
    }

    public function effectiveWidthMm(): ?int
    {
        $value = $this->width_mm ?? $this->product?->width_mm;

        return $value !== null ? (int) $value : null;
    }

    public function effectiveHeightMm(): ?int
    {
        $value = $this->height_mm ?? $this->product?->height_mm;

        return $value !== null ? (int) $value : null;
    }

    public function tracksInventory(): bool
    {
        return $this->inventory_quantity !== null;
    }

    public function availableInventory(): ?int
    {
        return $this->inventory_quantity !== null ? max(0, (int) $this->inventory_quantity) : null;
    }

    public function effectiveLowStockThreshold(): ?int
    {
        $value = $this->low_stock_threshold ?? $this->product?->effectiveLowStockThreshold();

        return $value !== null && (int) $value > 0 ? (int) $value : null;
    }

    public function isLowStock(): bool
    {
        $available = $this->availableInventory();
        $threshold = $this->effectiveLowStockThreshold();

        return $available !== null && $threshold !== null && $available <= $threshold;
    }

    public function isInStock(): bool
    {
        return $this->availableInventory() === null || $this->availableInventory() > 0;
    }

    public function isPreorder(): bool
    {
        return false;
    }

    public function allowsBackorder(): bool
    {
        return (bool) ($this->allow_backorder || $this->is_preorder);
    }

    public function preorderShippingEstimateLabel(string $format = 'F jS'): ?string
    {
        if (! $this->preorder_shipping_estimate instanceof Carbon) {
            return null;
        }

        return $this->preorder_shipping_estimate->format($format);
    }

    public function backorderShippingEstimateLabel(string $format = 'F jS'): ?string
    {
        $estimate = $this->backorderShippingEstimate();

        if (! $estimate instanceof Carbon) {
            return null;
        }

        return $estimate->format($format);
    }

    public function backorderShippingEstimate(): ?Carbon
    {
        if (($this->backorder_shipping_estimate_type === Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC || ($this->backorder_shipping_estimate_type === null && $this->backorder_shipping_offset_days !== null)) && $this->backorder_shipping_offset_days !== null) {
            return Carbon::today()->addDays(max(0, (int) $this->backorder_shipping_offset_days));
        }

        if ($this->backorder_shipping_estimate instanceof Carbon) {
            return $this->backorder_shipping_estimate;
        }

        if ($this->preorder_shipping_estimate instanceof Carbon) {
            return $this->preorder_shipping_estimate;
        }

        return null;
    }
}
