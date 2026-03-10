<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductVariant extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'name',
        'sku',
        'price',
        'compare_at_price',
        'shipping_rate',
        'inventory_quantity',
        'weight_grams',
        'length_cm',
        'width_cm',
        'height_cm',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'compare_at_price' => 'decimal:2',
        'shipping_rate' => 'decimal:2',
        'inventory_quantity' => 'integer',
        'weight_grams' => 'integer',
        'length_cm' => 'decimal:2',
        'width_cm' => 'decimal:2',
        'height_cm' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

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

    public function effectiveWeightGrams(): ?int
    {
        $value = $this->weight_grams;
        if ($value === null) {
            $value = $this->product?->weight_grams;
        }

        return $value !== null ? (int) $value : null;
    }

    public function effectiveLengthCm(): ?float
    {
        $value = $this->length_cm;
        if ($value === null) {
            $value = $this->product?->length_cm;
        }

        return $value !== null ? round((float) $value, 2) : null;
    }

    public function effectiveWidthCm(): ?float
    {
        $value = $this->width_cm;
        if ($value === null) {
            $value = $this->product?->width_cm;
        }

        return $value !== null ? round((float) $value, 2) : null;
    }

    public function effectiveHeightCm(): ?float
    {
        $value = $this->height_cm;
        if ($value === null) {
            $value = $this->product?->height_cm;
        }

        return $value !== null ? round((float) $value, 2) : null;
    }

    public function tracksInventory(): bool
    {
        return $this->inventory_quantity !== null;
    }

    public function availableInventory(): ?int
    {
        return $this->inventory_quantity !== null ? max(0, (int) $this->inventory_quantity) : null;
    }

    public function isInStock(): bool
    {
        return $this->availableInventory() === null || $this->availableInventory() > 0;
    }
}
