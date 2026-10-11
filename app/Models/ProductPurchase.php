<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductPurchase extends Model
{
    protected $fillable = ['expense_id', 'product_id', 'product_variant_id', 'quantity', 'total_cost_ex_tax', 'inventory_added', 'individual_units', 'purchase_pack', 'units_per_purchase'];

    protected $casts = ['quantity' => 'float', 'total_cost_ex_tax' => 'float', 'inventory_added' => 'integer', 'individual_units' => 'boolean', 'purchase_pack' => 'boolean', 'units_per_purchase' => 'integer'];

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class);
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
}
