<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'name', 'address', 'suburb', 'state', 'postcode', 'latitude', 'longitude', 'address_url', 'url',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
    ];

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }
}
