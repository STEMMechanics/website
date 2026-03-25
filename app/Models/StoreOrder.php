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

    public const STATUS_QUOTE_REQUESTED = 'quote_requested';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';

    public const STATUS_PARTIALLY_SHIPPED = 'partially_shipped';

    public const STATUS_SHIPPED = 'shipped';

    public const STATUS_COLLECTED = 'collected';

    public const STATUS_FULFILLED = 'fulfilled';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_QUOTE_REQUESTED,
        self::STATUS_PROCESSING,
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_PARTIALLY_SHIPPED,
        self::STATUS_SHIPPED,
        self::STATUS_COLLECTED,
        self::STATUS_FULFILLED,
        self::STATUS_CANCELLED,
    ];

    public const FULFILMENT_STATUSES = [
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_PARTIALLY_SHIPPED,
        self::STATUS_SHIPPED,
        self::STATUS_COLLECTED,
        self::STATUS_FULFILLED,
    ];

    protected $fillable = [
        'order_number',
        'access_token',
        'user_id',
        'invoice_id',
        'quote_id',
        'coupon_id',
        'status',
        'contains_digital',
        'contains_physical',
        'contains_preorder',
        'split_shipments',
        'consolidate_shipments',
        'shipment_count',
        'preorder_acknowledged',
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
        'shipping_method_code',
        'shipping_package_summary',
        'shipping_breakdown_data',
        'shipping_zone',
        'shipping_chargeable_weight_grams',
        'coupon_code',
        'coupon_type',
        'notes',
        'public_notes',
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
        'contains_preorder' => 'boolean',
        'split_shipments' => 'boolean',
        'consolidate_shipments' => 'boolean',
        'shipment_count' => 'integer',
        'preorder_acknowledged' => 'boolean',
        'shipping_breakdown_data' => 'array',
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
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
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

    /**
     * @return HasMany<StoreOrderUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(StoreOrderUpdate::class)->orderBy('occurred_at')->orderBy('id');
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
            self::STATUS_QUOTE_REQUESTED => 'Quote Requested',
            self::STATUS_PROCESSING => 'Preparing Order',
            self::STATUS_READY_FOR_PICKUP => 'Ready for Pickup',
            self::STATUS_PARTIALLY_SHIPPED => 'Partially Shipped',
            self::STATUS_SHIPPED => 'Shipped',
            self::STATUS_COLLECTED => 'Collected',
            self::STATUS_FULFILLED => 'Complete',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', (string) $this->status)),
        };
    }

    public function hasCoupon(): bool
    {
        return trim((string) $this->coupon_code) !== '' && (float) $this->discount_amount > 0;
    }

    public function usesPickup(): bool
    {
        return (string) $this->shipping_method_code === StoreShippingMethod::CODE_PICKUP;
    }

    public function hasMultipleShipments(): bool
    {
        return (int) $this->shipment_count > 1 || (bool) $this->split_shipments;
    }

    public function containsBackorder(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->contains(
                fn (StoreOrderItem $item) => $item->isBackorder()
            );
        }

        return $this->items()
            ->where('delayed_quantity', '>', 0)
            ->where('delayed_fulfilment_type', 'backorder')
            ->exists();
    }

    public function shippingBreakdown(): array
    {
        return is_array($this->shipping_breakdown_data) ? $this->shipping_breakdown_data : [];
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

    public function hasAnyDispatchedPhysicalQuantity(): bool
    {
        if ($this->relationLoaded('items')) {
            return $this->items->contains(
                fn (StoreOrderItem $item) => ! $item->isDigital() && $item->trackedQuantity() > 0
            );
        }

        return $this->items()
            ->with('trackingEntries')
            ->where('product_type', '!=', Product::PRODUCT_TYPE_DIGITAL)
            ->get()
            ->contains(fn (StoreOrderItem $item) => $item->trackedQuantity() > 0);
    }

    public function remainingPhysicalFulfillableQuantity(): int
    {
        if ($this->relationLoaded('items')) {
            return (int) $this->items
                ->filter(fn (StoreOrderItem $item) => ! $item->isDigital())
                ->sum(fn (StoreOrderItem $item) => $item->remainingFulfillableQuantity());
        }

        return (int) $this->items()
            ->with('trackingEntries')
            ->where('product_type', '!=', Product::PRODUCT_TYPE_DIGITAL)
            ->get()
            ->sum(fn (StoreOrderItem $item) => $item->remainingFulfillableQuantity());
    }

    public function isPartiallyShippedByEntries(): bool
    {
        return $this->hasAnyDispatchedPhysicalQuantity()
            && $this->remainingPhysicalFulfillableQuantity() > 0;
    }

    public function isFullyShippedByEntries(): bool
    {
        return $this->hasAnyDispatchedPhysicalQuantity()
            && $this->remainingPhysicalFulfillableQuantity() === 0;
    }
}
