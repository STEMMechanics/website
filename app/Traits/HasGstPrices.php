<?php

namespace App\Traits;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Finance\GstRate;
use Illuminate\Support\Facades\Schema;

trait HasGstPrices
{
    private function gstPrice(string $field, mixed $value, bool $decimal = true): mixed
    {
        if ($value === null) {
            return null;
        }
        $taxable = $this instanceof Product ? (float) ($this->attributes['tax_rate'] ?? GstRate::LEGACY) > 0
            : (! $this instanceof ProductVariant || (float) ($this->product->tax_rate ?? GstRate::current()) > 0);
        $multiplier = $taxable ? GstRate::priceMultiplier($this->attributes[$field.'_gst_effective_from'] ?? null) : 1.0;
        if (is_numeric($value)) {
            return $decimal ? number_format((float) $value * $multiplier, 2, '.', '')
                : (abs($multiplier - 1) < 0.00000001 ? $value : number_format((float) $value * $multiplier, 2, '.', ''));
        }
        if (! $decimal && preg_match('/^\$\s*([\d,]+(?:\.\d+)?)$/', trim((string) $value), $match) && abs($multiplier - 1) > 0.00000001) {
            return '$'.number_format((float) str_replace(',', '', $match[1]) * $multiplier, 2, '.', '');
        }

        return $value;
    }

    private function setGstPrice(string $field, mixed $value): void
    {
        $this->attributes[$field] = $value;
        // Some installation migrations create products before the GST columns exist.
        if (Schema::hasColumn($this->getTable(), $field.'_gst_effective_from')) {
            $this->attributes[$field.'_gst_effective_from'] = GstRate::effectiveFrom();
        }
    }

    public function getPriceAttribute(mixed $value): mixed
    {
        return $this->gstPrice('price', $value, $this instanceof Product || $this instanceof ProductVariant);
    }

    public function setPriceAttribute(mixed $value): void
    {
        $this->setGstPrice('price', $value);
    }
}
