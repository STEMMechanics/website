<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickListTemplateItem extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';

    public const TYPE_PER_PARTICIPANT = 'per_participant';

    public const TYPES = [
        self::TYPE_FIXED,
        self::TYPE_PER_PARTICIPANT,
    ];

    protected $fillable = [
        'product_id', 'product_variant_id', 'cut_size_id', 'length', 'length_unit', 'quantity_unit',
        'pick_list_template_id',
        'item_name',
        'stock_item_id',
        'quantity_type',
        'quantity_value',
        'stock_quantity',
        'sort_order',
    ];

    protected $casts = [
        'product_id' => 'integer', 'product_variant_id' => 'integer', 'cut_size_id' => 'integer', 'length' => 'float',
        'quantity_value' => 'integer',
        'stock_item_id' => 'integer',
        'stock_quantity' => 'decimal:3',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<PickListTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PickListTemplate::class, 'pick_list_template_id');
    }

    /** @return BelongsTo<StockItem, $this> */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVariant, $this> */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    /** @return BelongsTo<ProductCutSize, $this> */
    public function cutSize(): BelongsTo
    {
        return $this->belongsTo(ProductCutSize::class);
    }

    public function computedQuantity(int $participants): int
    {
        $participants = max(0, $participants);
        $value = max(1, (int) $this->quantity_value);

        if ((string) $this->quantity_type === self::TYPE_PER_PARTICIPANT) {
            return max(0, $value * $participants);
        }

        return $value;
    }
}
