<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'minimum_order_amount' => 'decimal:2',
        'usage_limit' => 'integer',
        'usage_limit_per_user' => 'integer',
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

    public function isEligibleForOrder(float $subtotal, ?User $user = null, ?string $billingEmail = null): bool
    {
        if (! $this->isAvailableNow()) {
            return false;
        }

        $minimum = $this->minimum_order_amount;
        if ($minimum !== null && $subtotal + 0.0001 < (float) $minimum) {
            return false;
        }

        if ($this->usage_limit !== null && $this->redeemedCount() >= (int) $this->usage_limit) {
            return false;
        }

        if ($this->usage_limit_per_user !== null && $this->redeemedCountFor($user, $billingEmail) >= (int) $this->usage_limit_per_user) {
            return false;
        }

        return true;
    }

    public function ineligibilityReason(float $subtotal, ?User $user = null, ?string $billingEmail = null): ?string
    {
        if ((string) $this->status !== self::STATUS_ACTIVE) {
            return 'That coupon is not active.';
        }

        if (! $this->isAvailableNow()) {
            return 'That coupon is not available right now.';
        }

        $minimum = $this->minimum_order_amount;
        if ($minimum !== null && $subtotal + 0.0001 < (float) $minimum) {
            return 'That coupon requires a minimum order of $'.number_format((float) $minimum, 2).'.';
        }

        if ($this->usage_limit !== null && $this->redeemedCount() >= (int) $this->usage_limit) {
            return 'That coupon has reached its usage limit.';
        }

        if ($this->usage_limit_per_user !== null && $this->redeemedCountFor($user, $billingEmail) >= (int) $this->usage_limit_per_user) {
            return 'You have already used that coupon the maximum number of times.';
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
