<?php

namespace App\MediaServices;

use App\Exceptions\MediaServiceException;
use App\Models\Media;

interface MediaService
{
    /**
     * Return if the supplied mime type is supported by this processor.
     *
     * @param string $mimeType The mime type to test.
     * @return boolean If the mime type is supported.
     */
    public function mimeSupported(string $mimeType): bool;

    /**
     * Return if the supplied service key is supported by this processor.
     *
     * @param string $key The service key to test.
     * @return boolean If the service key is supported.
     */
    public function serviceSupports(string $key): bool;

    /**
     * Process the media item.
     *
     * @throws MediaServiceException If the processing fails.
     *
     * @param Media $media The media model.
     * @param MediaServiceData $data The data for the media service.
     *
     * @return void
     */
    public function process(Media $media, MediaServiceData $data): void;
}
