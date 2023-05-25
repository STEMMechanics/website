<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AnalyticsSession extends Model
{
    use HasFactory;

    /**
     * Returns the related requests for this session.
     * 
     * @return Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function requests(): HasMany {
        return $this->hasMany(AnalyticsRequest::class, 'session_id', 'id');
    }
    
}
