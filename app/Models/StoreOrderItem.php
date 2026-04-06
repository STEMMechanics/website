<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class StoreOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_id',
        'product_id',
        'product_variant_id',
        'invoice_line_id',
        'product_title',
        'product_slug',
        'variant_name',
        'product_sku',
        'variant_sku',
        'product_type',
        'box_only',
        'is_preorder',
        'preorder_shipping_estimate',
        'quantity',
        'available_now_quantity',
        'delayed_quantity',
        'delayed_fulfilment_type',
        'delayed_shipping_estimate',
        'inventory_reserved_quantity',
        'cancelled_available_quantity',
        'cancelled_delayed_quantity',
        'unit_shipping_units',
        'unit_min_satchel_rank',
        'unit_price',
        'unit_shipping_rate',
        'tax_rate',
        'unit_weight_grams',
        'unit_length_cm',
        'unit_width_cm',
        'unit_height_cm',
        'line_price_amount',
        'line_shipping_amount',
        'line_gst_amount',
        'line_total_amount',
    ];

    protected $casts = [
        'box_only' => 'boolean',
        'is_preorder' => 'boolean',
        'preorder_shipping_estimate' => 'date',
        'quantity' => 'integer',
        'available_now_quantity' => 'integer',
        'delayed_quantity' => 'integer',
        'inventory_reserved_quantity' => 'integer',
        'cancelled_available_quantity' => 'integer',
        'cancelled_delayed_quantity' => 'integer',
        'delayed_shipping_estimate' => 'date',
        'unit_shipping_units' => 'decimal:3',
        'unit_min_satchel_rank' => 'integer',
        'unit_price' => 'decimal:2',
        'unit_shipping_rate' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'unit_weight_grams' => 'integer',
        'unit_length_cm' => 'decimal:2',
        'unit_width_cm' => 'decimal:2',
        'unit_height_cm' => 'decimal:2',
        'line_price_amount' => 'decimal:2',
        'line_shipping_amount' => 'decimal:2',
        'line_gst_amount' => 'decimal:2',
        'line_total_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<StoreOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }

    /**
     * @return HasMany<StoreOrderItemDownload, $this>
     */
    public function downloads(): HasMany
    {
        return $this->hasMany(StoreOrderItemDownload::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<StoreOrderItemTracking, $this>
     */
    public function trackingEntries(): HasMany
    {
        return $this->hasMany(StoreOrderItemTracking::class)->orderByDesc('dispatched_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<StoreOrderItemCancellation, $this>
     */
    public function cancellations(): HasMany
    {
        return $this->hasMany(StoreOrderItemCancellation::class)->orderByDesc('created_at')->orderByDesc('id');
    }

    /**
     * @return HasMany<StoreOrderUpdate, $this>
     */
    public function updates(): HasMany
    {
        return $this->hasMany(StoreOrderUpdate::class)->orderBy('occurred_at')->orderBy('id');
    }

    public function isDigital(): bool
    {
        return (string) $this->product_type === Product::PRODUCT_TYPE_DIGITAL;
    }

    public function displayTitle(): string
    {
        $variantName = trim((string) $this->variant_name);
        if ($variantName === '') {
            return (string) $this->product_title;
        }

        return (string) $this->product_title.' - '.$variantName;
    }

    public function reservedInventory(): int
    {
        return max(0, (int) $this->inventory_reserved_quantity);
    }

    public function availableQuantityTotal(): int
    {
        $available = max(0, (int) ($this->available_now_quantity ?? 0));
        $delayed = max(0, (int) ($this->delayed_quantity ?? 0));

        if ($available <= 0 && $delayed <= 0) {
            return max(0, (int) $this->quantity);
        }

        return $available;
    }

    public function delayedQuantityTotal(): int
    {
        return max(0, (int) ($this->delayed_quantity ?? 0));
    }

    public function cancelledAvailableQuantity(): int
    {
        return min($this->availableQuantityTotal(), max(0, (int) ($this->cancelled_available_quantity ?? 0)));
    }

    public function cancelledDelayedQuantity(): int
    {
        return min($this->delayedQuantityTotal(), max(0, (int) ($this->cancelled_delayed_quantity ?? 0)));
    }

    public function cancelledQuantity(): int
    {
        return $this->cancelledAvailableQuantity() + $this->cancelledDelayedQuantity();
    }

    public function trackedAvailableQuantity(): int
    {
        return min($this->availableQuantityTotal(), $this->trackingQuantityForShipmentType(StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE));
    }

    public function trackedDelayedQuantity(): int
    {
        return min($this->delayedQuantityTotal(), $this->trackingQuantityForShipmentType(StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED));
    }

    public function trackedQuantity(): int
    {
        return $this->trackedAvailableQuantity() + $this->trackedDelayedQuantity();
    }

    public function remainingAvailableQuantity(): int
    {
        return max(0, $this->availableQuantityTotal() - $this->cancelledAvailableQuantity() - $this->trackedAvailableQuantity());
    }

    public function remainingDelayedQuantity(): int
    {
        return max(0, $this->delayedQuantityTotal() - $this->cancelledDelayedQuantity() - $this->trackedDelayedQuantity());
    }

    public function remainingFulfillableQuantity(): int
    {
        return $this->remainingAvailableQuantity() + $this->remainingDelayedQuantity();
    }

    public function isFullyCancelled(): bool
    {
        return $this->cancelledQuantity() >= max(0, (int) $this->quantity);
    }

    public function shipsLater(): bool
    {
        return (int) $this->delayed_quantity > 0;
    }

    public function isBackorder(): bool
    {
        return $this->shipsLater() && (string) $this->delayed_fulfilment_type === 'backorder';
    }

    public function delayedFulfilmentLabel(): ?string
    {
        if (! $this->shipsLater()) {
            return null;
        }

        return match ((string) $this->delayed_fulfilment_type) {
            'preorder' => 'Pre-order shipment',
            'backorder' => 'Backorder shipment',
            default => 'Delayed shipment',
        };
    }

    public function delayedShippingEstimateLabel(string $format = 'F jS'): ?string
    {
        if (! $this->delayed_shipping_estimate instanceof Carbon) {
            return null;
        }

        return $this->delayed_shipping_estimate->format($format);
    }

    public function preorderShippingEstimateLabel(string $format = 'F jS'): ?string
    {
        if (! $this->preorder_shipping_estimate instanceof Carbon) {
            return null;
        }

        return $this->preorder_shipping_estimate->format($format);
    }

    /**
     * @return Collection<int, StoreOrderItemTracking>
     */
    private function trackingEntriesCollection(): Collection
    {
        if ($this->relationLoaded('trackingEntries')) {
            return $this->trackingEntries;
        }

        return $this->trackingEntries()->get();
    }

    private function trackingQuantityForShipmentType(string $shipmentType): int
    {
        return max(
            0,
            (int) $this->trackingEntriesCollection()
                ->where('shipment_type', $shipmentType)
                ->sum(fn (StoreOrderItemTracking $tracking) => max(0, (int) $tracking->quantity))
        );
    }
}
