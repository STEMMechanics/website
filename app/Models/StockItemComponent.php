<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockItemComponent extends Model
{
    use HasFactory;

    protected $fillable = ['kit_stock_item_id', 'component_stock_item_id', 'quantity', 'note', 'sort_order'];

    protected $casts = ['quantity' => 'decimal:3', 'sort_order' => 'integer'];

    /** @return BelongsTo<StockItem, $this> */
    public function kit(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'kit_stock_item_id');
    }

    /** @return BelongsTo<StockItem, $this> */
    public function component(): BelongsTo
    {
        return $this->belongsTo(StockItem::class, 'component_stock_item_id');
    }
}
