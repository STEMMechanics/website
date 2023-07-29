<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;

class Attachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'media_id',
        'private',
    ];

    /**
     * The default attributes.
     *
     * @var string[]
     */
    protected $attributes = [
        'private' => false,
    ];


    /**
     * Get the media for this attachment.
     *
     * @return BelongsTo
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /**
     * Get associated Media object.
     *
     * @return null|Media
     */
    public function getMedia(): ?Media
    {
        return Cache::remember("attachment:{$this->id}:media", now()->addDays(28), function () {
            return $this->media()->first();
        });
    }
}
