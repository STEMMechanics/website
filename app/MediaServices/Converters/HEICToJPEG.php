<?php

namespace App\MediaServices\Converters;

use App\Exceptions\MediaServiceException;
use App\Http\Controllers\MediaController;
use App\MediaServices\MediaService;
use App\MediaServices\MediaServiceData;
use App\Models\Media;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\Facades\Image;
use Intervention\Image\ImageManager;
use Intervention\Image\Interfaces\ImageInterface;

class HEICToJPEG implements MediaService
{
    /**
     * Return if the supplied mime type is supported by this processor.
     *
     * @param string $mimeType The mime type to test.
     * @return boolean If the mime type is supported.
     */
    public function mimeSupported(string $mimeType): bool
    {
        return $mimeType === 'image/heic' || $mimeType === 'image/heif';
    }

    /**
     * Return if the supplied service key is supported by this processor.
     *
     * @param string $key The service key to test.
     * @return boolean If the service key is supported.
     */
    public function serviceSupports(string $key): bool {
        return in_array($key, [
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

        $quality = $data->option('heictojpeg', 'quality', 90);
        $encoded = $image->toJpeg($quality);
        $encoded->save($data->file()); // this needs to be renamed with the new extension

        if(!$data->nextSupports(ImageInterface::class)) {
            $image->save();
            $data->removeData(ImageInterface::class);
        }

        Image::make($tempFile)
            ->save($tempJpgFile);

        $media->set
        $media->mime_type = 'image/jpeg';



        /*****/


        $filePath = $file['dirname'] . '/' . $file['name'] . '.' . $file['extension'];
        $jpgFileName = MediaController::makeNewFilename($file['name'], 'jpg');

        Image::make($filePath)
            ->save($file['dirname'] . '/' . $jpgFileName);

        $file['name'] = pathinfo($jpgFileName, PATHINFO_FILENAME);
        $file['extension'] = 'jpg';
        $file['mime_type'] = 'image/jpeg';
        $file['size'] = filesize($file['dirname'] . '/' . $jpgFileName);

        unlink($filePath);

        return true;
    }
}
