<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasAttachments
{
    /**
     * Boot function from Laravel.
     *
     * @return void
     */
    protected static function bootHasAttachments(): void
    {
        static::deleting(function ($model) {
            $model->attachments()->delete();
        });
    }

    /**
     * Add multiple attachments items to the model.
     *
     * @param array|string $ids             The media ids to add.
     * @param string       $delimiter       The split delimiter if $ids is a string.
     * @param boolean      $allowDuplicates Whether to allow duplicate media IDs or not.
     * @return void
     */
    public function attachmentsAddMany(array|string $ids, string $delimiter = ',', bool $allowDuplicates = false): void
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

        $this->attachments()->createMany($galleryItems);
    }

    /**
     * Get the article's attachments.
     *
     * @return Illuminate\Database\Eloquent\Relations\MorphMany The attachments items
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(\App\Models\Attachment::class, 'addendum');
    }
}
