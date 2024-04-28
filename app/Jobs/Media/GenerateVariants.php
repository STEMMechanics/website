<?php

namespace App\Jobs\Media;

use App\Models\Media;
use App\Helpers;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
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
        }

        $media->status = 'ready';
        $media->save();
    }
}
