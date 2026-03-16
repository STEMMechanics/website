<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class StoreOrderUpdate extends Model
{
    use HasFactory;

    public const EVENT_BACKORDER_ALLOCATED = 'backorder_allocated';

    public const EVENT_TRACKING_ADDED = 'tracking_added';

    public const EVENT_ITEM_CANCELLED = 'item_cancelled';

    public const EVENT_STATUS_CHANGED = 'status_changed';

    public const EVENT_PUBLIC_NOTE_UPDATED = 'public_note_updated';

    protected $fillable = [
        'store_order_id',
        'store_order_item_id',
        'event_type',
        'customer_visible',
        'payload',
        'occurred_at',
        'customer_digest_queued_at',
        'admin_digest_queued_at',
    ];

    protected $casts = [
        'customer_visible' => 'boolean',
        'payload' => 'array',
        'occurred_at' => 'datetime',
        'customer_digest_queued_at' => 'datetime',
        'admin_digest_queued_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<StoreOrder, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(StoreOrder::class, 'store_order_id');
    }

    /**
     * @return BelongsTo<StoreOrderItem, $this>
     */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(StoreOrderItem::class, 'store_order_item_id');
    }

    public function occurredAtLabel(string $format = 'g:i a'): ?string
    {
        return $this->occurred_at instanceof Carbon
            ? $this->occurred_at->format($format)
            : null;
    }
}
