<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOrderItemCollection extends Model
{
    use HasFactory;

    public const COLLECTION_TYPE_AVAILABLE = 'available';

    public const COLLECTION_TYPE_DELAYED = 'delayed';

    public const PICKUP_STATE_READY = 'ready';

    public const PICKUP_STATE_COLLECTED = 'collected';

    protected $fillable = [
        'store_order_item_id',
        'collection_type',
        'quantity',
        'pickup_state',
        'collected_by_user_id',
        'notes',
        'collected_at',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'collected_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StoreOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(StoreOrderItem::class, 'store_order_item_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function collectedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collected_by_user_id');
    }

    public function collectionTypeLabel(): string
    {
        return match ((string) $this->collection_type) {
            self::COLLECTION_TYPE_DELAYED => 'Backorder collection',
            default => 'Reserved stock collection',
        };
    }

    public function pickupStateLabel(): string
    {
        return match ((string) $this->pickup_state) {
            self::PICKUP_STATE_READY => 'Ready for pickup',
            default => 'Collected',
        };
    }
}
