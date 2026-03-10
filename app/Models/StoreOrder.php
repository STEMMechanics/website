<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PROCESSING,
        self::STATUS_FULFILLED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'order_number',
        'access_token',
        'user_id',
        'invoice_id',
        'coupon_id',
        'status',
        'contains_digital',
        'contains_physical',
        'billing_name',
        'billing_email',
        'billing_phone',
        'billing_company',
        'shipping_name',
        'shipping_phone',
        'shipping_address',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postcode',
        'shipping_country',
        'shipping_method',
        'shipping_package_summary',
        'shipping_zone',
        'shipping_chargeable_weight_grams',
        'coupon_code',
        'coupon_type',
        'notes',
        'subtotal_amount',
        'shipping_amount',
        'discount_amount',
        'gst_amount',
        'total_amount',
        'paid_at',
        'fulfilled_at',
        'order_confirmation_emailed_at',
        'order_paid_emailed_at',
    ];

    protected $casts = [
        'contains_digital' => 'boolean',
        'contains_physical' => 'boolean',
        'shipping_chargeable_weight_grams' => 'integer',
        'subtotal_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'fulfilled_at' => 'datetime',
        'order_confirmation_emailed_at' => 'datetime',
        'order_paid_emailed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return BelongsTo<Coupon, $this>
     */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }

    /**
     * @return HasMany<StoreOrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(StoreOrderItem::class)->orderBy('id');
    }

    public function getRouteKeyName(): string
    {
        return 'order_number';
    }

    public function isPaid(): bool
    {
        if ($this->relationLoaded('invoice') && $this->invoice instanceof Invoice) {
            return $this->invoice->outstandingAmount() <= 0.0001;
        }

        return $this->invoice()->exists()
            && ((float) $this->invoice()->first()->outstandingAmount()) <= 0.0001;
    }

    public function isAccessibleBy(?User $user, ?string $accessToken = null): bool
    {
        if ($user?->isAdmin()) {
            return true;
        }

        if ($user && (string) ($this->user_id ?? '') !== '' && (string) $this->user_id === (string) $user->id) {
            return true;
        }

        return trim((string) $accessToken) !== ''
            && hash_equals((string) $this->access_token, trim((string) $accessToken));
    }

    public function statusLabel(): string
    {
        return match ((string) $this->status) {
            self::STATUS_PENDING_PAYMENT => 'Pending Payment',
            self::STATUS_PROCESSING => 'Processing',
            self::STATUS_FULFILLED => 'Fulfilled',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function hasCoupon(): bool
    {
        return trim((string) $this->coupon_code) !== '' && (float) $this->discount_amount > 0;
    }

    public function shippingAddressLines(): array
    {
        return array_values(array_filter([
            (string) ($this->shipping_name ?? ''),
            trim(implode(' ', array_filter([
                (string) ($this->shipping_address ?? ''),
                (string) ($this->shipping_address2 ?? ''),
            ]))),
            trim(implode(', ', array_filter([
                (string) ($this->shipping_city ?? ''),
                (string) ($this->shipping_state ?? ''),
                (string) ($this->shipping_postcode ?? ''),
            ]))),
            (string) ($this->shipping_country ?? ''),
        ]));
    }
}
