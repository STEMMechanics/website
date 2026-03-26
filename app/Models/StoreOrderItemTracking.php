<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOrderItemTracking extends Model
{
    use HasFactory;

    public const SHIPMENT_TYPE_AVAILABLE = 'available';

    public const SHIPMENT_TYPE_DELAYED = 'delayed';

    protected $fillable = [
        'store_order_item_id',
        'shipment_type',
        'quantity',
        'parcel_number',
        'carrier',
        'tracking_number',
        'tracking_url',
        'notes',
        'dispatched_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'parcel_number' => 'integer',
        'dispatched_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StoreOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(StoreOrderItem::class, 'store_order_item_id');
    }

    public function shipmentTypeLabel(): string
    {
        return match ((string) $this->shipment_type) {
            self::SHIPMENT_TYPE_DELAYED => 'Backorder dispatch',
            default => 'Reserved stock dispatch',
        };
    }
}
