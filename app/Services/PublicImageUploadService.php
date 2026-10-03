<?php

namespace App\Services;

use App\Exceptions\FileInvalidException;
use Illuminate\Http\UploadedFile;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Throwable;

final class PublicImageUploadService
{
    /**
     * @var array<int, array{mime:string, extension:string}>
     */
    private const ALLOWED_FORMATS = [
        IMAGETYPE_JPEG => ['mime' => 'image/jpeg', 'extension' => 'jpg'],
        IMAGETYPE_PNG => ['mime' => 'image/png', 'extension' => 'png'],
        IMAGETYPE_WEBP => ['mime' => 'image/webp', 'extension' => 'webp'],
    ];

    /**
     * Decode and re-encode a public image so the stored file contains only a
     * supported raster format and no unnecessary source metadata.
     *
     * @throws FileInvalidException
     */
    public function sanitize(UploadedFile $file): UploadedFile
    {
        $sourcePath = $file->getRealPath() ?: $file->path();
        $imageType = @exif_imagetype($sourcePath);
        $format = is_int($imageType) ? (self::ALLOWED_FORMATS[$imageType] ?? null) : null;

        if ($format === null || ! is_file($sourcePath)) {
            throw new FileInvalidException('Public uploads must be a valid JPG, PNG, or WebP image.');
        }

        $detectedMime = @mime_content_type($sourcePath);
        if ($detectedMime !== $format['mime']) {
            throw new FileInvalidException('Public uploads must be a valid JPG, PNG, or WebP image.');
        }

        $temporaryPath = tempnam(sys_get_temp_dir(), 'public-image-');
        if ($temporaryPath === false) {
            throw new FileInvalidException('The public image could not be processed.');
        }

        $targetPath = $temporaryPath.'.'.$format['extension'];
        if (! @rename($temporaryPath, $targetPath)) {
            @unlink($temporaryPath);

            throw new FileInvalidException('The public image could not be processed.');
        }

        try {
            $image = (new ImageManager(new Driver()))->read($sourcePath);
            $image->orient();
            $nativeImage = $image->core()->native();
            if (! ($nativeImage instanceof \Imagick)) {
                throw new FileInvalidException('The public image could not be processed.');
            }

            $nativeImage->stripImage();
            $image->save($targetPath, quality: 90);

            if (@exif_imagetype($targetPath) !== $imageType || @mime_content_type($targetPath) !== $format['mime']) {
                throw new FileInvalidException('The public image could not be processed.');
            }
        } catch (FileInvalidException $exception) {
            @unlink($targetPath);

            throw $exception;
        } catch (Throwable $exception) {
            @unlink($targetPath);

            throw new FileInvalidException('The public image could not be processed.');
        }

        $baseName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $canonicalName = ($baseName !== '' ? $baseName : 'upload').'.'.$format['extension'];

        return new UploadedFile($targetPath, $canonicalName, $format['mime'], null, true);
    }
}
