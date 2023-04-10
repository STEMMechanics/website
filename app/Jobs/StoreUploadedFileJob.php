<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use SplFileInfo;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Intervention\Image\Facades\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class StoreUploadedFileJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Media item
     *
     * @var Media
     */
    protected $media;

    /**
     * Uploaded file item
     *
     * @var string
     */
    protected $uploadedFilePath;


    /**
     * Create a new job instance.
     *
     * @param Media  $media    The media model.
     * @param string $filePath The uploaded file.
     * @return void
     */
    public function __construct(Media $media, string $filePath)
    {
        $this->media = $media;
        $this->uploadedFilePath = $filePath;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $storageDisk = $this->media->storage;
        $fileName = $this->media->name;

        try {
            $this->media->status = "Uploading to CDN";
            $this->media->save();
            Storage::disk($storageDisk)->putFileAs('/', new SplFileInfo($this->uploadedFilePath), $fileName);
            Log::info("uploading file {$storageDisk} / {$fileName} / {$this->uploadedFilePath}");

            if (strpos($this->media->mime_type, 'image/') === 0) {
                $this->media->status = "Optimizing image";
                $this->media->save();

                // Generate additional image sizes
                $sizes = [
                    'thumb' => [150, 150],
                    'small' => [300, 225],
                    'medium' => [768, 576],
                    'large' => [1024, 768],
                    'xlarge' => [1536, 1152],
                    'xxlarge' => [2048, 1536],
                    'scaled' => [2560, 1920]
                ];

                $variants = [];

                $originalImage = Image::make($this->uploadedFilePath);
                $optimizerChain = OptimizerChainFactory::create();

                $dimensions = [$originalImage->getWidth(), $originalImage->getHeight()];
                $this->media->dimensions = implode('x', $dimensions);

                foreach ($sizes as $variantName => $size) {
                    $postfix = "{$size[0]}x{$size[1]}";
                    if ($variantName === 'scaled') {
                        $postfix = 'scaled';
                    }

                    $newFilename = pathinfo($this->media->name, PATHINFO_FILENAME) . "-$postfix." . pathinfo($this->media->name, PATHINFO_EXTENSION);

                    // Get the largest available variant
                    if ($dimensions[0] >= $size[0] && $dimensions[1] >= $size[1]) {
                        // $largestVariant = $newFilename;

                        // Resize the image to the variant size if its dimensions are greater than the specified size
                        $image = clone $originalImage;

                        $imageSize = $image->getSize();
                        if ($imageSize->getWidth() > $size[0] || $imageSize->getHeight() > $size[1]) {
                            $image->resize($size[0], $size[1], function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                            $image->resizeCanvas($size[0], $size[1], 'center', false, '#FFFFFF');
                        }

                        // Store the variant in the variants array
                        $variants[$variantName] = $newFilename;

                        // Optimize and store image
                        $tempImagePath = tempnam(sys_get_temp_dir(), 'optimize');
                        $image->save($tempImagePath);
                        $optimizerChain->optimize($tempImagePath);
                        Storage::disk($storageDisk)->putFileAs('/', new SplFileInfo($tempImagePath), $newFilename);
                        unlink($tempImagePath);
                    }//end if
                }//end foreach

                // Set missing variants to the largest available variant
                foreach ($sizes as $variantName => $size) {
                    if (isset($variants[$variantName]) === false) {
                        $variants[$variantName] = $this->media->name;
                    }
                }

                $this->media->variants = $variants;
            }//end if

            if ($this->uploadedFilePath !== '') {
                unlink($this->uploadedFilePath);
            }

            $this->media->status = '';
            $this->media->save();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->media->status = "Failed";
            $this->media->save();
            $this->fail($e);
        }//end try
    }
}
