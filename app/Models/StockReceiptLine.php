<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockReceiptLine extends Model
{
    use HasFactory;

    protected $fillable = ['stock_receipt_id', 'stock_item_id', 'quantity', 'unit_cost_ex_tax', 'total_cost_ex_tax'];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_cost_ex_tax' => 'decimal:4',
        'total_cost_ex_tax' => 'decimal:4',
    ];

    /** @return BelongsTo<StockReceipt, $this> */
    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
