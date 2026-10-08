<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const TYPE_RECEIPT = 'receipt';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public const TYPE_ASSEMBLY = 'assembly';

    public const TYPE_SALE = 'sale';

    public const TYPE_WORKSHOP = 'workshop';

    protected $fillable = ['stock_item_id', 'stock_receipt_line_id', 'movement_type', 'quantity', 'unit_cost_ex_tax', 'source_type', 'source_id', 'created_by', 'occurred_at', 'notes'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost_ex_tax' => 'decimal:4',
        'occurred_at' => 'datetime',
    ];

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return BelongsTo<StockReceiptLine, $this> */
    public function receiptLine(): BelongsTo
    {
        return $this->belongsTo(StockReceiptLine::class, 'stock_receipt_line_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
