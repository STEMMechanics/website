<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreShippingMethod extends Model
{
    use HasFactory;

    public const CODE_REGULAR = 'regular';

    public const CODE_EXPRESS = 'express';

    public const CODE_PICKUP = 'pickup';

    public const CALCULATOR_PACKAGES = 'packages';

    public const CALCULATOR_SATCHEL = 'satchel';

    public const CALCULATOR_PICKUP = 'pickup';

    public const CALCULATOR_FLAT_RATE = 'flat_rate';

    protected $fillable = [
        'code',
        'name',
        'description',
        'shipment_label',
        'immediate_status_label',
        'delayed_status_label',
        'calculator',
        'flat_rate_amount',
        'delivery_estimate_min_days',
        'delivery_estimate_max_days',
        'rate_multiplier',
        'rate_adjustment_amount',
        'is_pickup',
        'is_default',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'flat_rate_amount' => 'decimal:2',
        'delivery_estimate_min_days' => 'integer',
        'delivery_estimate_max_days' => 'integer',
        'rate_multiplier' => 'decimal:2',
        'rate_adjustment_amount' => 'decimal:2',
        'is_pickup' => 'boolean',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function isPickup(): bool
    {
        return (bool) $this->is_pickup || (string) $this->calculator === self::CALCULATOR_PICKUP;
    }

    /**
     * @return HasMany<StoreShippingMethodPackage, $this>
     */
    public function packageOptions(): HasMany
    {
        return $this->hasMany(StoreShippingMethodPackage::class)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function usesPackageCalculation(): bool
    {
        return in_array((string) $this->calculator, [self::CALCULATOR_PACKAGES, self::CALCULATOR_SATCHEL], true);
    }

    public function usesSatchelCalculation(): bool
    {
        return $this->usesPackageCalculation();
    }

    public function usesFlatRateCalculation(): bool
    {
        return (string) $this->calculator === self::CALCULATOR_FLAT_RATE;
    }

    public function deliveryEstimateLabel(): ?string
    {
        $min = $this->delivery_estimate_min_days;
        $max = $this->delivery_estimate_max_days;

        if ($min === null && $max === null) {
            return null;
        }

        if ($min !== null && $max !== null) {
            if ($min === $max) {
                return $min.' business day'.($min === 1 ? '' : 's');
            }

            return $min.'-'.$max.' business days';
        }

        if ($min !== null) {
            return $min.'+ business days';
        }

        return 'Up to '.$max.' business days';
    }

    public function shipmentLabel(): string
    {
        $label = trim((string) ($this->shipment_label ?? ''));

        if ($label !== '') {
            return $label;
        }

        return $this->isPickup() ? 'Collection' : 'Shipment';
    }

    public function immediateStatusLabel(): string
    {
        $label = trim((string) ($this->immediate_status_label ?? ''));

        if ($label !== '') {
            return $label;
        }

        return $this->isPickup() ? 'Available now' : 'Ships now';
    }

    public function delayedStatusLabel(): string
    {
        $label = trim((string) ($this->delayed_status_label ?? ''));

        if ($label !== '') {
            return $label;
        }

        return $this->isPickup() ? 'Available later' : 'Ships later';
    }

    public function adjustedAmount(float $baseAmount): float
    {
        $multiplied = round($baseAmount * max(0, (float) $this->rate_multiplier), 2);

        return round(max(0, $multiplied + (float) $this->rate_adjustment_amount), 2);
    }
}
