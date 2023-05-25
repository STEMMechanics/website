<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSession extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'ip',
        'useragent',
    ];

    /**
     * Returns the related requests for this session.
     * 
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function requests(): HasMany {
        return $this->hasMany(AnalyticsItemRequest::class, 'session_id', 'id');
    }
    
}
