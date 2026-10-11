<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCutSize extends Model
{
    protected $fillable = ['product_id', 'length_mm', 'width_mm', 'yield', 'sort_order'];

    protected $casts = ['length_mm' => 'float', 'width_mm' => 'float', 'yield' => 'integer'];

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function label(): string
    {
        return ProductRecipeComponent::formatNumber((float) $this->length_mm).' × '.ProductRecipeComponent::formatNumber((float) $this->width_mm).' mm';
    }
}
