<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'parent_id',
        'filename',
        'path',
        'type',
        'metadata',
        'size'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'metadata' => 'array',
    ];


    /**
     * Get the original media of this variation.
     *
     * @return BelongsTo
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'parent_id');
    }

    /**
     * Get the variations for the media.
     *
     * @return HasMany
     */
    public function variations(): HasMany
    {
        return $this->hasMany(Media::class, 'parent_id');
    }
}
