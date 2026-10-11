<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductRecipeComponent extends Model
{
    public const LENGTH_UNITS = ['mm' => 1, 'cm' => 10, 'm' => 1000];

    protected $fillable = ['product_id', 'component_product_id', 'component_variant_id', 'cut_size_id', 'quantity', 'length', 'length_unit', 'note', 'sort_order'];

    protected $casts = ['quantity' => 'float', 'length' => 'float'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'component_product_id');
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'component_variant_id');
    }

    /** @return BelongsTo<ProductCutSize, $this> */
    public function cutSize(): BelongsTo
    {
        return $this->belongsTo(ProductCutSize::class);
    }

    public static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 3, '.', ''), '0'), '.');
    }

    public function preparationLabel(): string
    {
        $size = $this->cutSize?->label();
        if ($this->length !== null) {
            $size = self::formatNumber((float) $this->length).' '.(string) $this->length_unit;
        }

        $name = $size && $this->component?->isPhysical() && ! $this->variant
            ? $this->component->title
            : ($this->component?->displayTitle($this->variant) ?? 'Missing item');

        return trim(($size ? $size.' ' : '').$name);
    }

    /** Purchase units or retail packs for costing only. The pick list counts pieces. */
    public function purchasedUnits(): ?float
    {
        $material = $this->component;
        if (! $material instanceof Product) {
            return null;
        }
        if (! $material->hasMaterialMeasurements()) {
            return (float) $this->quantity;
        }
        $packUnits = $material->isPhysical() ? $material->saleUnits($this->variant) : 1;
        if ($material->material_kind === 'sheet') {
            if (! $this->cut_size_id) {
                return (float) $this->quantity;
            }

            return $this->cutSize && $this->cutSize->yield > 0
                ? (float) $this->quantity / $this->cutSize->yield / $packUnits
                : null;
        }
        $purchased = (float) $material->purchase_quantity;
        if ($purchased <= 0) {
            return null;
        }
        if ($material->material_kind === 'length') {
            // Existing store-item links without a length still refer to complete packs.
            if ($material->isPhysical() && $this->length === null) {
                return (float) $this->quantity;
            }
            $purchaseFactor = self::LENGTH_UNITS[(string) $material->material_measure_unit] ?? 0;
            $cutFactor = self::LENGTH_UNITS[(string) $this->length_unit] ?? 0;

            return $this->length > 0 && $purchaseFactor > 0 && $cutFactor > 0
                ? (float) $this->quantity * (float) $this->length * $cutFactor / ($purchased * $purchaseFactor) / $packUnits
                : null;
        }

        return (float) $this->quantity / $purchased;
    }

    public function preparationNote(float $multiples = 1): string
    {
        $material = $this->component;
        $units = $this->purchasedUnits();
        if (! $material?->hasMaterialMeasurements() || $units === null) {
            return '';
        }
        if ($material->material_kind === 'length') {
            if ($this->length === null) {
                return '';
            }
            $millimetres = (float) $this->quantity * (float) $this->length
                * self::LENGTH_UNITS[(string) $this->length_unit] * $multiples;

            return $millimetres >= 1000
                ? self::formatNumber($millimetres / 1000).' m total'
                : self::formatNumber($millimetres).' mm total';
        }
        if ($material->material_kind === 'sheet' || $material->purchase_quantity > 1) {
            if ($material->isPhysical() && ! $this->cut_size_id) {
                return '';
            }
            $packUnits = $material->isPhysical() ? $material->saleUnits($this->variant) : 1;

            return 'Allow '.self::formatNumber(ceil($units * $packUnits * $multiples - 0.0000001)).' × '.$material->purchaseUnitLabel();
        }

        return '';
    }
}
