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
    public function getMediaAttribute(): ?Media
    {
        $mediaId = '0';
        $media = null;

        if (Cache::has("attachment:{$this->id}:media") === true) {
            $mediaId = Cache::get("attachment:{$this->id}:media");
        } else {
            $media = $this->media()->first();
            if ($media === null) {
                return null;
            }

            $mediaId = $media->id;
            Cache::put("attachment:{$this->id}:media", $mediaId, now()->addDays(28));
        }

        return Cache::remember("media:{$mediaId}", now()->addDays(28), function () use ($media) {
            if ($media !== null) {
                return $media;
            }

            return $this->media()->first();
        });
    }

    /**
     * Set the media for this item.
     *
     * @param Media $media The media model.
     * @return void
     */
    public function setMediaAttribute(Media $media): void
    {
        $this->media()->associate($media)->save();
        Cache::put("attachment:{$this->id}:media", $media->id, now()->addDays(28));
    }
}
