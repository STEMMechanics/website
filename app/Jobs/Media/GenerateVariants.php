<?php

namespace App\Jobs\Media;

use App\Models\Media;
use App\Helpers;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Imagick\Driver;

class GenerateVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Media ID
     *
     * @var String
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
     *
     * @param Media $media The media to process
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
        return [new WithoutOverlapping($this->media_name)];
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

        if(Storage::disk('media')->exists($media->hash) === false) {
            return;
        }

        $variantData = $media->getVariantTypes($matchingMimeType);
        if(count($variantData) === 0) {
            return;
        }

        $temp = $media->getAsTempFile();
        if($temp === null) {
            return;
        }

        $tempDir = pathinfo($temp, PATHINFO_DIRNAME);
        $media->deleteAllVariants();

        /* Images */
        if($matchingMimeType === 'image/*') {
            $manager = new ImageManager(new Driver());
            $image = $manager->read($temp);

            $isPortrait = $image->height() > $image->width();

            foreach ($variantData as $variantName => $size) {
                $image = $manager->read($temp);

                if($isPortrait === true) {
                    $width = $size['height'];
                    $height = $size['width'];
                } else {
                    $width = $size['width'];
                    $height = $size['height'];
                }

                if($variantName !== 'scaled' && ($image->height() < $height || $image->width() < $width)) {
                    continue;
                }

                $image->scaleDown($width, $height);
                $variantFile = $tempDir . '/' . $media->hash . '-' . $variantName . '.webp';
                $image->save($variantFile, quality: 75);

                $media->addVariant($variantName, 'image/webp', 'webp', $variantFile);
            }//end foreach
        } else if($matchingMimeType === 'text/plain') {
            /* Text */
            $width = $variantData['thumbnail']['width'];
            $height = $variantData['thumbnail']['height'];

            $manager = new ImageManager(new Driver());
            $image = $manager->create($width, $height)->fill('fff');

            // Read the first few lines of the text file
            $numLines = 5;
            $text = file_get_contents($temp);
            $lines = explode("\n", $text);
            $previewText = implode("\n", array_slice($lines, 0, $numLines));

            // Center the text on the image
            $fontSize = 8;
            $textColor = '#000000'; // Black text color

            // Calculate the position to start drawing the text
            $x = 10; // Left padding
            $y = 10; // Top padding

            // Draw the text on the canvas with text wrapping
            $lines = explode("\n", wordwrap($previewText, 30, "\n", true));
            foreach ($lines as $line) {
                $image->text($line, $x, $y, function ($font) use ($fontSize, $textColor) {
                    $font->file(1);
                    $font->size($fontSize);
                    $font->color($textColor);
                });

                // Move to the next line
                $y += ($fontSize + 4); // Add some vertical spacing between lines (adjust as needed)
            }

            $variantFile = $tempDir . '/' . $media->hash . '-thumbnail.webp';
            $image->save($variantFile, quality: 75);
            $media->addVariant('thumbnail', 'image/webp', 'webp', $variantFile);

        } else if($matchingMimeType === 'application/pdf') {
            /* PDF */
            $width = $variantData['thumbnail']['width'];
            $height = $variantData['thumbnail']['height'];

            $manager = new ImageManager(new Driver());
            $image = $manager->read($temp);
            $image->scaleDown($width, $height);

            $variantFile = $tempDir . '/' . $media->hash . '-thumbnail.webp';
            $image->save($variantFile, quality: 75);
            $media->addVariant('thumbnail', 'image/webp', 'webp', $variantFile);

        } else if($matchingMimeType === 'video/*') {
            /* Video */
            $tempImage = $tempDir . '/' . $media->hash . '-temp-frame.jpg';
            $variantFile = $tempDir . '/' . $media->hash . '-thumbnail.webp';

            try {
                $ffmpeg = FFMpeg::create();
                $video = $ffmpeg->open($temp);
                $frame = $video->frame(TimeCode::fromSeconds(5));
                $frame->save($variantFile);

                $width = $variantData['thumbnail']['width'];
                $height = $variantData['thumbnail']['height'];

                $manager = new ImageManager(new Driver());
                $image = $manager->read($tempImage);
                $image->scaleDown($width, $height);
                $image->save($variantFile, quality: 75);

                $media->addVariant('thumbnail', 'image/webp', 'webp', $variantFile);
            } catch (\Exception $e) {
                Log::error($e);
            }

            if(file_exists($tempImage)) {
                unlink($tempImage);
            }
        }

        $media->status = 'ready';
        $media->save();
    }
}
