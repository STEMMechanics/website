<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    protected $fillable = ['parent_id', 'filename', 'path', 'type', 'metadata', 'size'];

    protected $casts = [
        'metadata' => 'array',
    ];

    /**
     * Get the original media of this variation.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'parent_id');
    }

    /**
     * Get the variations for the media.
     */
    

    public function variations(): HasMany
    {
        return $this->hasMany(Media::class, 'original_media_id');
    }
}
