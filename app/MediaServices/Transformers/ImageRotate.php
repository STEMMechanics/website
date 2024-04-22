<?php

namespace App\MediaServices\Transformers;

use App\Exceptions\MediaServiceException;
use App\MediaServices\MediaService;
use App\MediaServices\MediaServiceData;
use App\Models\Media;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Interfaces\ImageInterface;

class ImageRotate implements MediaService
{
    /**
     * Return if the supplied mime type is supported by this processor.
     *
     * @param string $mimeType The mime type to test.
     * @return boolean If the mime type is supported.
     */
    public function mimeSupported(string $mimeType): bool {
        return in_array($mimeType, [
            'image/jpeg',
            'image/webp',
            'image/gif',
            'image/png',
            'image/avif',
            'image/heic',
            'image/bmp'
        ]);
    }

    /**
     * Return if the supplied service key is supported by this processor.
     *
     * @param string $key The service key to test.
     * @return boolean If the service key is supported.
     */
    public function serviceSupports(string $key): bool {
        return in_array($key, [
            'rotate',
            ImageInterface::class
        ]);
    }

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
    public function process(Media $media, MediaServiceData $data): void
    {
        $image = $data->getData(ImageInterface::class, function() use ($data) {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($data->file());
            if($image === null) {
                throw new MediaServiceException('Could not read file.');
            }

            return $image;
        });

        $degrees = $data->option('rotate', 'degrees', 90);
        $image->rotate($degrees);

        if(!$data->nextSupports(ImageInterface::class)) {
            $image->save();
            $data->removeData(ImageInterface::class);
        }
    }
}
