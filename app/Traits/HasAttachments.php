<?php

namespace App\Traits;

use App\Models\Media;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Cache;

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
            $model->deleteAttachments();
        });
    }

    /**
     * Get the attachments associated to this item.
     *
     * @return Collection
     */
    public function getAttachments(): Collection
    {
        return Cache::remember($this->cacheKey(), now()->addDays(28), function () {
            return $this->attachments()->get();
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
    public function addAttachments(array|string $ids, string $delimiter = ',', bool $allowDuplicates = false): void
    {
        if (is_array($ids) === false) {
            $ids = explode($delimiter, $ids);
        }

        $ids = array_map('trim', $ids);
        $existingIds = $this->attachmentsGet()->pluck('media_id')->toArray();

        $attachmentItems = [];
        foreach ($ids as $id) {
            if ($allowDuplicates === false && in_array($id, $existingIds) === true) {
                continue;
            }

            if (Media::where('id', $id)->exists() === true) {
                $attachmentItems[] = ['media_id' => $id];
            }
        }

        Cache::forget($this->cacheKey());
        $this->attachments()->createMany($attachmentItems);
    }

    /**
     * Delete associated attachments.
     *
     * @return void
     */
    public function deleteAttachments(): void
    {
        Cache::forget($this->cacheKey());
        $this->morphMany(\App\Models\Attachment::class, 'addendum')->delete();
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

    /**
     * Return the attachment cache key.
     *
     * @return string
     */
    private function cacheKey(): string
    {
        return "attachments:{$this->getTable()}:{$this->id}";
    }
}
