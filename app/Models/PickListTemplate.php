<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickListTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];

    /**
     * @return HasMany<PickListTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PickListTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
