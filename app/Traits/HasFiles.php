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

        if ($files instanceof \Illuminate\Support\Collection) {
            $files = $files->all();
        }

        if (is_string($files)) {
            $trimmedFiles = html_entity_decode(trim($files), ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($trimmedFiles === '') {
                $files = [];
            } else {
                $decodedFiles = json_decode($trimmedFiles, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decodedFiles)) {
                    $files = $decodedFiles;
                } else {
                    $files = Helpers::stringToArray($trimmedFiles);
                }
            }
        }

        if (is_array($files)) {
            $files = array_values(array_filter(array_map(function ($file) {
                if (is_string($file)) {
                    return trim($file);
                }

                if (is_array($file)) {
                    return isset($file['name']) ? trim((string) $file['name']) : null;
                }

                if (is_object($file) && isset($file->name)) {
                    return trim((string) $file->name);
                }

                return null;
            }, $files), fn ($fileName) => is_string($fileName) && $fileName !== ''));

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
