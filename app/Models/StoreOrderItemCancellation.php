<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreOrderItemCancellation extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_order_item_id',
        'cancelled_by_user_id',
        'available_quantity',
        'delayed_quantity',
        'reason',
    ];

    protected $casts = [
        'available_quantity' => 'integer',
        'delayed_quantity' => 'integer',
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
    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function quantity(): int
    {
        return max(0, (int) $this->available_quantity) + max(0, (int) $this->delayed_quantity);
    }
}
