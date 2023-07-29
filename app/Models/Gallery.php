<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Cache;

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
     * Boot the model.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        $clearCache = function ($gallery) {
            Cache::forget("gallery:{$gallery->id}:media");
        };

        static::saving($clearCache);
        static::deleting($clearCache);
    }

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

    /**
     * Get the media for this item.
     *
     * @return null|Media The media model.
     */
    public function getMedia(): ?Media
    {
        $mediaId = '0';
        $media = null;

        if (Cache::has("gallery:{$this->id}:media") === true) {
            $mediaId = Cache::get("gallery:{$this->id}:media");
        } else {
            $media = $this->media()->first();
            if ($media === null) {
                return null;
            }

            $mediaId = $media->id;
            Cache::put("gallery:{$this->id}:media", $mediaId, now()->addDays(28));
        }

        return Cache::remember("media:{$mediaId}", now()->addDays(28), function () use ($media) {
            if ($media !== null) {
                return $media;
            }

            return $this->media()->first();
        });
    }
}
