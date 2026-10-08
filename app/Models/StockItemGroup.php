<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockItemGroup extends Model
{
    use HasFactory;

    public const KIND_ITEMS = 'items';

    protected $fillable = ['name', 'kind'];

    protected $casts = ['kind' => 'string'];

    /** @return HasMany<StockItem, $this> */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class)->orderBy('variant_name')->orderBy('name');
    }
}
