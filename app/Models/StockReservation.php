<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReservation extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_PARTIALLY_CONSUMED = 'partially_consumed';

    public const STATUS_RELEASED = 'released';

    public const STATUS_CONSUMED = 'consumed';

    public const ACTIVE_STATUSES = [self::STATUS_ACTIVE, self::STATUS_PARTIALLY_CONSUMED];

    protected $fillable = ['stock_item_id', 'source_type', 'source_id', 'purpose', 'status', 'quantity', 'remaining_quantity', 'unit_cost_snapshot', 'metadata', 'reserved_at', 'released_at', 'consumed_at', 'created_by'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'remaining_quantity' => 'decimal:3',
        'unit_cost_snapshot' => 'decimal:4',
        'metadata' => 'array',
        'reserved_at' => 'datetime',
        'released_at' => 'datetime',
        'consumed_at' => 'datetime',
    ];

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isActive(): bool
    {
        return in_array((string) $this->status, self::ACTIVE_STATUSES, true)
            && (float) $this->remaining_quantity > 0;
    }
}
