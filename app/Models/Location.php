<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Location extends Model
{
    use HasFactory, UUID;

    protected $fillable = ['name', 'address', 'address_url', 'url'];

    public function workshops(): HasMany
    {
        return $this->hasMany(Workshop::class);
    }
}
