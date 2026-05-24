<?php

namespace App\Jobs\Media;

use App\Models\Media;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class GenerateVariants implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Media ID
     *
     * @var string
     */
    public $media_name;

    /**
     * Overwrite existing
     *
     * @var bool
     */
    public $overwrite;

    /**
     * Create a new job instance.
     */
    public function __construct(Media $media, bool $overwrite = true)
    {
        $this->media_name = $media->name;
        $this->overwrite = $overwrite;
    }

    /**
     * Get the middleware the job should pass through.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->media_name))->dontRelease()->expireAfter(3600)];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $media = Media::find($this->media_name);
        if ($media === null) {
            return;
        }

        $temp = null;
        $processingError = null;

        $media->status = 'processing';
        $media->last_processing_batch_id = $this->batch()?->id;
        $media->last_processing_error = null;
        $media->last_processing_failed_at = null;
        $media->save();

        try {
            if (Storage::disk('media')->exists((string) $media->hash) === false) {
                $processingError = 'Source file is missing from media storage.';
                return;
            }

            $variantData = $media->getVariantTypes($matchingMimeType);
            if (count($variantData) === 0) {
                return;
            }

            $temp = $media->getAsTempFile();
            if ($temp === null) {
                $processingError = 'Could not create a temporary working copy of the media file.';
                return;
            }

            $tempDir = pathinfo($temp, PATHINFO_DIRNAME);
            $targetVariants = array_keys($variantData);
            if ($this->overwrite) {
                $media->deleteAllVariants();
            } else {
                $targetVariants = array_values(array_filter($targetVariants, function (string $variantName) use ($media): bool {
                    return ! $this->isVariantReady($media, $variantName);
                }));
            }

            if (count($targetVariants) === 0) {
                return;
            }

            if ($matchingMimeType === 'image/*') {
                $this->generateImageVariants($media, $temp, $tempDir, $variantData, $targetVariants);
                return;
            }

            if ($matchingMimeType === 'text/plain') {
                if (! in_array('thumbnail', $targetVariants, true)) {
                    return;
                }

                $width = (int) $variantData['thumbnail']['width'];
                $height = (int) $variantData['thumbnail']['height'];

                $manager = new ImageManager(new Driver());
                $image = $manager->create($width, $height)->fill('fff');

                $numLines = 5;
                $text = (string) file_get_contents($temp);
                $lines = explode("\n", $text);
                $previewText = implode("\n", array_slice($lines, 0, $numLines));

                $fontSize = 8;
                $textColor = '#000000';
                $x = 10;
                $y = 10;

                $lines = explode("\n", wordwrap($previewText, 30, "\n", true));
                foreach ($lines as $line) {
                    $image->text($line, $x, $y, function ($font) use ($fontSize, $textColor) {
                        $font->file(1);
                        $font->size($fontSize);
                        $font->color($textColor);
                    });
                    $y += ($fontSize + 4);
                }

                $variantFile = $tempDir.'/'.$media->hash.'-thumbnail.webp';
                $image->save($variantFile, quality: 75);
                $media->addVariant('thumbnail', 'image/webp', 'webp', $variantFile);
                return;
            }

            if ($matchingMimeType === 'application/pdf') {
                if (! in_array('thumbnail', $targetVariants, true)) {
                    return;
                }

                $width = (int) $variantData['thumbnail']['width'];
                $height = (int) $variantData['thumbnail']['height'];

                $manager = new ImageManager(new Driver());

                $imagick = new \Imagick();
                $imagick->readImage($temp.'[0]');
                $imagick->setImageFormat('png');

                $image = $manager->read($imagick);
                $image->scaleDown($width, $height);

                $variantFile = $tempDir.'/'.$media->hash.'-thumbnail.webp';
                $image->save($variantFile, quality: 75);
                $media->addVariant('thumbnail', 'image/webp', 'webp', $variantFile);
                return;
            }

            if ($matchingMimeType === 'video/*') {
                if (! in_array('thumbnail', $targetVariants, true)) {
                    return;
                }

                $tempImage = $tempDir.'/'.$media->hash.'-temp-frame.jpg';
                $variantFile = $tempDir.'/'.$media->hash.'-thumbnail.webp';

                try {
                    $ffmpeg = FFMpeg::create();
                    $video = $ffmpeg->open($temp);
                    if (! method_exists($video, 'frame')) {
                        throw new \RuntimeException('Video driver does not support frame extraction.');
                    }

                    $frameAt = 1.0;
                    try {
                        $ffprobe = FFProbe::create();
                        $duration = (float) $ffprobe->format($temp)->get('duration');
                        if ($duration > 0.0) {
                            $frameAt = max(0.0, min(1.0, $duration - 0.1));
                        } else {
                            $frameAt = 0.0;
                        }
                    } catch (Throwable) {
                        $frameAt = 1.0;
                    }

                    $frame = $video->frame(TimeCode::fromSeconds($frameAt));
                    $frame->save($tempImage);

                    $width = (int) $variantData['thumbnail']['width'];
                    $height = (int) $variantData['thumbnail']['height'];

                    $manager = new ImageManager(new Driver());
                    $image = $manager->read($tempImage);
                    $image->scaleDown($width, $height);
                    $image->save($variantFile, quality: 75);

                    $media->addVariant('thumbnail', 'image/webp', 'webp', $variantFile);
                } catch (Throwable $e) {
                    $processingError = $this->buildProcessingError('Video thumbnail generation failed.', $e);
                    Log::warning($processingError);
                }

                if (is_file($tempImage)) {
                    @unlink($tempImage);
                }
                return;
            }

            $processingError = 'Variant generation is not supported for MIME type: '.((string) $media->mime_type);
        } catch (Throwable $e) {
            $processingError = $this->buildProcessingError('Media variant generation failed.', $e);
            Log::warning($processingError);
        } finally {
            $media->status = 'ready';
            if ($processingError === null) {
                $media->last_processing_error = null;
                $media->last_processing_failed_at = null;
            } else {
                $media->last_processing_error = $processingError;
                $media->last_processing_failed_at = now();
            }
            $media->save();

            if (is_string($temp) && is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    /**
     * @param array<string, array<string, int>> $variantData
     * @param array<int, string> $targetVariants
     */
    private function generateImageVariants(Media $media, string $temp, string $tempDir, array $variantData, array $targetVariants): void
    {
        $manager = new ImageManager(new Driver());
        $baseImage = $manager->read($temp);

        $isPortrait = $baseImage->height() > $baseImage->width();

        foreach ($variantData as $variantName => $size) {
            if (! in_array($variantName, $targetVariants, true)) {
                continue;
            }

            $image = $manager->read($temp);

            if ($isPortrait === true) {
                $width = (int) $size['height'];
                $height = (int) $size['width'];
            } else {
                $width = (int) $size['width'];
                $height = (int) $size['height'];
            }

            if ($variantName !== 'scaled' && ($image->height() < $height || $image->width() < $width)) {
                continue;
            }

            $image->scaleDown($width, $height);
            $variantFile = $tempDir.'/'.$media->hash.'-'.$variantName.'.webp';
            $image->save($variantFile, quality: 75);

            $media->addVariant($variantName, 'image/webp', 'webp', $variantFile);
        }
    }

    private function buildProcessingError(string $message, Throwable $e): string
    {
        return mb_substr(trim($message.' '.$e->getMessage()), 0, 2000);
    }

    private function isVariantReady(Media $media, string $variantName): bool
    {
        $variants = is_array($media->variants) ? $media->variants : [];

        return array_key_exists($variantName, $variants) && $media->hasVariant($variantName);
    }
}
