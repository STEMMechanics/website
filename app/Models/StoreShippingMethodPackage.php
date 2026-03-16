<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreShippingMethodPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_shipping_method_id',
        'code',
        'label',
        'sort_order',
        'capacity',
        'price',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'capacity' => 'decimal:2',
        'price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * @return BelongsTo<StoreShippingMethod, $this>
     */
    public function shippingMethod(): BelongsTo
    {
        return $this->belongsTo(StoreShippingMethod::class, 'store_shipping_method_id');
    }
}
