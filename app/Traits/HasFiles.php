<?php

namespace App\Traits;

use App\Helpers;
use App\Models\Media;
use Illuminate\Support\Str;

trait HasFiles
{
    public function files($collection = null)
    {
//        return $this->morphToMany(Media::class, 'mediable')
//            ->wherePivot('collection', $collection);

        return $this->morphToMany(Media::class, 'mediable')
            ->selectRaw("*, CASE WHEN password IS NULL THEN NULL ELSE 'yes' END AS password")
            ->wherePivot('collection', $collection);
    }

    public function updateFiles($files, $collection = null): void
    {
        if($files === null) {
            $files = '';
        }

        if (is_string($files)) {
            $files = Helpers::stringToArray($files);
        }

        if (is_array($files)) {
            // Remove duplicates from the array
            $files = array_unique($files);

            // Detach all existing attachments
            $this->files($collection)->detach();
            foreach ($files as $fileName) {
                $media = Media::find($fileName);
                if ($media) {
                    $this->files($collection)->attach($media->name, ['collection' => $collection]);
                }
            }
        }
    }
}
