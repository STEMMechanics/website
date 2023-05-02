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
     * Replace existing files
     *
     * @var string
     */
    protected $replaceExisting;


    /**
     * Create a new job instance.
     *
     * @param Media   $media           The media model.
     * @param string  $filePath        The uploaded file.
     * @param boolean $replaceExisting Replace existing files.
     * @return void
     */
    public function __construct(Media $media, string $filePath, bool $replaceExisting = true)
    {
        $this->media = $media;
        $this->uploadedFilePath = $filePath;
        $this->replaceExisting = $replaceExisting;
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

            if (strlen($this->uploadedFilePath) > 0) {
                if (Storage::disk($storageDisk)->exists($fileName) === false || $this->replaceExisting === true) {
                    Storage::disk($storageDisk)->putFileAs('/', new SplFileInfo($this->uploadedFilePath), $fileName);
                    Log::info("uploading file {$storageDisk} / {$fileName} / {$this->uploadedFilePath}");
                } else {
                    Log::info("file {$fileName} already exists in {$storageDisk} / {$this->uploadedFilePath}. Not replacing file and using local {$fileName} for variants.");
                }
            } else {
                if (Storage::disk($storageDisk)->exists($fileName) === true) {
                    Log::info("file {$fileName} already exists in {$storageDisk} / {$this->uploadedFilePath}. No local {$fileName} for variants, downloading from CDN.");
                    $readStream = Storage::disk($storageDisk)->readStream($fileName);
                    $tempFilePath = tempnam(sys_get_temp_dir(), 'download-');
                    $writeStream = fopen($tempFilePath, 'w');
                    while (feof($readStream) !== true) {
                        fwrite($writeStream, fread($readStream, 8192));
                    }
                    fclose($readStream);
                    fclose($writeStream);
                    $this->uploadedFilePath = $tempFilePath;
                } else {
                    $errorStr = "cannot upload file {$storageDisk} / {$fileName} / {$this->uploadedFilePath} as temp file is empty";
                    Log::info($errorStr);
                    throw new \Exception($errorStr);
                }
            }//end if

            if (strpos($this->media->mime_type, 'image/') === 0) {
                $this->media->status = "Optimizing image";
                $this->media->save();

                // Generate additional image sizes
                $sizes = Media::getTypeVariants('image');

                $originalImage = Image::make($this->uploadedFilePath);

                $dimensions = [$originalImage->getWidth(), $originalImage->getHeight()];
                $this->media->dimensions = implode('x', $dimensions);

                foreach ($sizes as $variantName => $size) {
                    $postfix = "{$size['width']}x{$size['height']}";
                    if ($variantName === 'scaled') {
                        $postfix = 'scaled';
                    }

                    if (is_array($this->media->variants) === true && array_key_exists($postfix, $this->media->variants) === true && Storage::disk($storageDisk)->exists($this->media->variants[$postfix]) === true && $this->replaceExisting === true) {
                        Storage::disk($storageDisk)->delete($this->media->variants[$postfix]);
                    }

                    $newFilename = pathinfo($this->media->name, PATHINFO_FILENAME) . "-$postfix.webp";

                    if (Storage::disk($storageDisk)->exists($newFilename) === false || $this->replaceExisting === true) {
                        // Get the largest available variant
                        if ($dimensions[0] >= $size['width'] && $dimensions[1] >= $size['height']) {
                            // Store the variant in the variants array
                            $variants[$variantName] = $newFilename;

                            // Resize the image to the variant size if its dimensions are greater than the specified size
                            $image = clone $originalImage;

                            $imageSize = $image->getSize();
                            if ($imageSize->getWidth() > $size['width'] || $imageSize->getHeight() > $size['height']) {
                                $image->resize($size['width'], $size['height'], function ($constraint) {
                                    $constraint->aspectRatio();
                                    $constraint->upsize();
                                });
                                $image->resizeCanvas($size['width'], $size['height'], 'center', false, '#FFFFFF');
                            }

                            // Optimize and store image
                            $tempImagePath = tempnam(sys_get_temp_dir(), 'optimize');
                            $image->encode('webp', 75)->save($tempImagePath);
                            Storage::disk($storageDisk)->putFileAs('/', new SplFileInfo($tempImagePath), $newFilename);
                            unlink($tempImagePath);
                        }//end if
                    } else {
                        Log::info("variant {$variantName} already exists for file {$fileName}");
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

            if (strlen($this->uploadedFilePath) > 0) {
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
