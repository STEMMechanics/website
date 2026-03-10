<?php

namespace App\Support;

use App\Models\Product;
use App\Models\SiteOption;
use Illuminate\Support\Facades\Schema;
use Throwable;

class ShopAvailability
{
    public const PUBLIC_ENABLED_OPTION = 'store.public-enabled';

    private const LEGACY_PUBLIC_ENABLED_OPTION = 'shop.public-enabled';

    private ?bool $publicEnabled = null;

    private ?bool $hasSaleableProducts = null;

    public function isPublicEnabled(): bool
    {
        if ($this->publicEnabled !== null) {
            return $this->publicEnabled;
        }

        try {
            if (! Schema::hasTable('site_options')) {
                return $this->publicEnabled = true;
            }

            $default = SiteOption::defaultValue(self::PUBLIC_ENABLED_OPTION) ?? '1';
            $raw = trim((string) SiteOption::value(self::PUBLIC_ENABLED_OPTION, ''));
            if ($raw === '') {
                $raw = trim((string) SiteOption::value(self::LEGACY_PUBLIC_ENABLED_OPTION, $default));
            }

            return $this->publicEnabled = filter_var($raw, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? false;
        } catch (Throwable) {
            return $this->publicEnabled = true;
        }
    }

    public function hasSaleableProducts(): bool
    {
        if ($this->hasSaleableProducts !== null) {
            return $this->hasSaleableProducts;
        }

        try {
            if (! Schema::hasTable('products')) {
                return $this->hasSaleableProducts = false;
            }

            return $this->hasSaleableProducts = Product::query()
                ->active()
                ->exists();
        } catch (Throwable) {
            return $this->hasSaleableProducts = false;
        }
    }

    public function isPubliclyAvailable(): bool
    {
        return $this->isPublicEnabled() && $this->hasSaleableProducts();
    }
}
