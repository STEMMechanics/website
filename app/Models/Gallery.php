<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Gallery extends Model
{
    use HasFactory;
    use Uuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'media_id',
    ];


    /**
     * Get gallery addendum model.
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphTo Addenum model.
     */
    public function addendum(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the media for this item.
     *
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo The media model.
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
