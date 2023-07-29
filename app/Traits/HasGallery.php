<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

trait HasGallery
{
    /**
     * Boot function from Laravel.
     *
     * @return void
     */
    protected static function bootHasGallery(): void
    {
        static::deleting(function ($model) {
            $model->gallery()->delete();
        });
    }

    /**
     * Add multiple gallery items to the model.
     *
     * @param array|string $ids             The media ids to add.
     * @param string       $delimiter       The split delimiter if $ids is a string.
     * @param boolean      $allowDuplicates Whether to allow duplicate media IDs or not.
     * @return void
     */
    public function galleryAddMany(array|string $ids, string $delimiter = ',', bool $allowDuplicates = false): void
    {
        if (is_array($ids) === false) {
            $ids = explode($delimiter, $ids);
        }

        $ids = array_map('trim', $ids);
        $existingIds = $this->gallery()->pluck('media_id')->toArray();

        $galleryItems = [];
        foreach ($ids as $id) {
            if ($allowDuplicates === false && in_array($id, $existingIds) === true) {
                continue;
            }

            $media = Media::find($id);
            if ($media !== null) {
                $galleryItems[] = ['media_id' => $id];
            }
        }

        $this->gallery()->createMany($galleryItems);
    }

    /**
     * Get the article's gallery.
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphMany The gallery items
     */
    public function gallery(): MorphMany
    {
        return $this->morphMany(\App\Models\Gallery::class, 'addendum');
    }

    public function getGallery(): Collection
    {
        $cacheKey = 'gallery:' . $this->getTable() . ':' . $this->id;
        if (Cache::has($cacheKey) === true) {
            return Cache::get($cacheKey);
        }

        $gallery = $this->morphMany(\App\Models\Gallery::class, 'addendum')->get();
        Cache::put($cacheKey, $gallery, now()->addDays(14));
        return $gallery;
    }
}
