<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Coupon extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const DISCOUNT_TYPE_FIXED_AMOUNT = 'fixed_amount';

    public const DISCOUNT_TYPE_PERCENTAGE = 'percentage';

    public const DISCOUNT_TYPE_FREE_SHIPPING = 'free_shipping';

    public const CHECKOUT_CONTEXT_PRODUCTS = 'products';

    public const CHECKOUT_CONTEXT_WORKSHOPS = 'workshops';

    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_INACTIVE,
    ];

    public const DISCOUNT_TYPES = [
        self::DISCOUNT_TYPE_FIXED_AMOUNT,
        self::DISCOUNT_TYPE_PERCENTAGE,
        self::DISCOUNT_TYPE_FREE_SHIPPING,
    ];

    protected $fillable = [
        'code',
        'description',
        'status',
        'discount_type',
        'amount',
        'minimum_order_amount',
        'usage_limit',
        'usage_limit_per_user',
        'applies_to_products',
        'applies_to_workshops',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
        'applies_to_products' => 'boolean',
        'applies_to_workshops' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    /**
     * @return HasMany<StoreOrder, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * @return BelongsToMany<Product, $this>
     */
    public function restrictedProducts(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'coupon_product_restrictions')
            ->withTimestamps()
            ->orderBy('products.title');
    }

    /**
     * @return BelongsToMany<Workshop, $this>
     */
    public function restrictedWorkshops(): BelongsToMany
    {
        return $this->belongsToMany(Workshop::class, 'coupon_workshop_restrictions')
            ->withTimestamps()
            ->orderBy('workshops.starts_at')
            ->orderBy('workshops.title');
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public static function normalizeCode(?string $code): string
    {
        return strtoupper(trim((string) $code));
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public static function discountTypeLabel(string $type): string
    {
        return match ($type) {
            self::DISCOUNT_TYPE_FIXED_AMOUNT => 'Fixed Amount',
            self::DISCOUNT_TYPE_PERCENTAGE => 'Percentage',
            self::DISCOUNT_TYPE_FREE_SHIPPING => 'Free Shipping',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    public function appliesToProducts(): bool
    {
        return (bool) $this->applies_to_products;
    }

    public function appliesToWorkshops(): bool
    {
        return (bool) $this->applies_to_workshops;
    }

    /**
     * @return array<int, int>
     */
    public function restrictedProductIds(): array
    {
        return ($this->relationLoaded('restrictedProducts') ? $this->restrictedProducts : $this->restrictedProducts()->get())
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function restrictedWorkshopIds(): array
    {
        return ($this->relationLoaded('restrictedWorkshops') ? $this->restrictedWorkshops : $this->restrictedWorkshops()->get())
            ->pluck('id')
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn (string $id) => $id !== '')
            ->values()
            ->all();
    }

    public function appliesToCheckoutContext(string $context, array $contextData = []): bool
    {
        if ($context === self::CHECKOUT_CONTEXT_PRODUCTS) {
            if (! $this->appliesToProducts()) {
                return false;
            }

            $restrictedProductIds = $this->restrictedProductIds();
            if ($restrictedProductIds === []) {
                return true;
            }

            $productIds = collect($contextData['product_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->filter()
                ->unique()
                ->values()
                ->all();

            return $productIds !== [] && array_diff($productIds, $restrictedProductIds) === [];
        }

        if ($context === self::CHECKOUT_CONTEXT_WORKSHOPS) {
            if (! $this->appliesToWorkshops()) {
                return false;
            }

            $restrictedWorkshopIds = $this->restrictedWorkshopIds();
            if ($restrictedWorkshopIds === []) {
                return true;
            }

            $workshopId = trim((string) ($contextData['workshop_id'] ?? ''));

            return $workshopId !== '' && in_array($workshopId, $restrictedWorkshopIds, true);
        }

        return true;
    }

    public function appliesToCheckoutContextMessage(string $context, array $contextData = []): string
    {
        if ($context === self::CHECKOUT_CONTEXT_PRODUCTS) {
            return $this->appliesToProducts()
                ? 'That voucher cannot be used for these products.'
                : 'That voucher cannot be used for products.';
        }

        if ($context === self::CHECKOUT_CONTEXT_WORKSHOPS) {
            return $this->appliesToWorkshops()
                ? 'That voucher cannot be used for this workshop.'
                : 'That voucher cannot be used for workshop tickets.';
        }

        return 'That voucher cannot be used for this checkout.';
    }

    public function isAvailableNow(?Carbon $now = null): bool
    {
        $now ??= now();

        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return false;
        }

        if ($this->starts_at instanceof Carbon && $this->starts_at->gt($now)) {
            return false;
        }

        if ($this->ends_at instanceof Carbon && $this->ends_at->lt($now)) {
            return false;
        }

        return true;
    }

    public function discountAmountFor(float $subtotal, float $shipping): float
    {
        return match ((string) $this->discount_type) {
            self::DISCOUNT_TYPE_PERCENTAGE => round(max(0, $subtotal) * ((float) $this->amount / 100), 2),
            self::DISCOUNT_TYPE_FREE_SHIPPING => round(max(0, $shipping), 2),
            default => round(min(max(0, $subtotal), max(0, (float) $this->amount)), 2),
        };
    }

    public function isEligibleForOrder(
        float $subtotal,
        ?User $user = null,
        ?string $billingEmail = null,
        string $checkoutContext = self::CHECKOUT_CONTEXT_PRODUCTS,
        array $checkoutContextData = []
    ): bool
    {
        return $this->ineligibilityReason($subtotal, $user, $billingEmail, $checkoutContext, $checkoutContextData) === null;
    }

    public function ineligibilityReason(
        float $subtotal,
        ?User $user = null,
        ?string $billingEmail = null,
        string $checkoutContext = self::CHECKOUT_CONTEXT_PRODUCTS,
        array $checkoutContextData = []
    ): ?string
    {
        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return 'That voucher is not active.';
        }

        if (! $this->isAvailableNow()) {
            return 'That voucher is not available right now.';
        }

        if (! $this->appliesToCheckoutContext($checkoutContext, $checkoutContextData)) {
            return $this->appliesToCheckoutContextMessage($checkoutContext, $checkoutContextData);
        }

        $minimum = $this->minimum_order_amount;
        if ($minimum !== null && $subtotal + 0.0001 < (float) $minimum) {
            return 'That voucher requires a minimum order of $'.number_format((float) $minimum, 2).'.';
        }

        if ($this->usage_limit !== null && $this->redeemedCount() >= (int) $this->usage_limit) {
            return 'That voucher has reached its usage limit.';
        }

        if ($this->usage_limit_per_user !== null && $this->redeemedCountFor($user, $billingEmail) >= (int) $this->usage_limit_per_user) {
            return 'You have already used that voucher the maximum number of times.';
        }

        return null;
    }

    public function redeemedCount(): int
    {
        return (int) $this->orders()
            ->where('status', '!=', StoreOrder::STATUS_CANCELLED)
            ->count();
    }

    public function redeemedCountFor(?User $user = null, ?string $billingEmail = null): int
    {
        $query = $this->orders()->where('status', '!=', StoreOrder::STATUS_CANCELLED);

        if ($user instanceof User) {
            return (int) $query->where('user_id', (string) $user->id)->count();
        }

        $email = strtolower(trim((string) $billingEmail));
        if ($email === '') {
            return 0;
        }

        return (int) $query->whereRaw('LOWER(billing_email) = ?', [$email])->count();
    }
}
