<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Exception\NotWritableException;
use Intervention\Image\Exception\NotSupportedException;
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
     * Modifications to make on the Media
     *
     * @var array
     */
    protected $modifications;


    /**
     * Create a new job instance.
     *
     * @param Media   $media           The media model.
     * @param string  $filePath        The uploaded file.
     * @param boolean $replaceExisting Replace existing files.
     * @param array   $modifications   The modifications to make on the media.
     * @return void
     */
    public function __construct(Media $media, string $filePath, bool $replaceExisting = true, array $modifications = [])
    {
        $this->media = $media;
        $this->uploadedFilePath = $filePath;
        $this->replaceExisting = $replaceExisting;
        $this->modifications = $modifications;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $storageDisk = $this->media->storage;
        $fileName = $this->media->name;

        try {
            $this->media->status = "Transferring to CDN";
            $this->media->save();

            // convert HEIC file
            $fileExtension = File::extension($this->uploadedFilePath);
            if ($fileExtension === 'heic') {
                // Get the path without the file name
                $uploadedFileDirectory = dirname($this->uploadedFilePath);

                // Convert the HEIC file to JPG
                $jpgFileName = pathinfo($this->uploadedFilePath, PATHINFO_FILENAME) . '.jpg';
                $jpgFilePath = $uploadedFileDirectory . '/' . $jpgFileName;
                Image::make($this->uploadedFilePath)->save($jpgFilePath);

                // Update the uploaded file path and file name
                $this->uploadedFilePath = $jpgFilePath;
                $fileName = $jpgFileName;
                $this->media->name = $fileName;
                $this->media->save();
            }

            if (strlen($this->uploadedFilePath) > 0) {
                if (Storage::disk($storageDisk)->exists($fileName) === false || $this->replaceExisting === true) {
                    /** @var Illuminate\Filesystem\FilesystemAdapter */
                    $fileSystem = Storage::disk($storageDisk);
                    $fileSystem->putFileAs('/', new SplFileInfo($this->uploadedFilePath), $fileName);
                    Log::info("uploading file {$storageDisk} / {$fileName} / {$this->uploadedFilePath}");
                } else {
                    Log::info("file {$fileName} already exists in {$storageDisk} / " . // phpcs:ignore
                    "{$this->uploadedFilePath}. Not replacing file and using local {$fileName} for variants.");
                }
            } else {
                if (Storage::disk($storageDisk)->exists($fileName) === true) {
                    Log::info("file {$fileName} already exists in {$storageDisk} / " . // phpcs:ignore
                    "{$this->uploadedFilePath}. No local {$fileName} for variants, downloading from CDN.");
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
                    $errorStr = "cannot upload file {$storageDisk} " . // phpcs:ignore
                    "/ {$fileName} / {$this->uploadedFilePath} as temp file is empty";
                    Log::info($errorStr);
                    throw new \Exception($errorStr);
                }
            }//end if

            $this->media->status = "Optimizing image";
            $this->media->save();
            $this->media->generateVariants($this->uploadedFilePath);

            $this->media->status = "Generating Thumbnail";
            $this->media->save();
            $this->media->generateThumbnail($this->uploadedFilePath);

            if (strlen($this->uploadedFilePath) > 0) {
                unlink($this->uploadedFilePath);
            }

            $this->media->status = 'OK';
            $this->media->save();
        } catch (\Exception $e) {
            Log::error($e->getMessage());
            $this->media->status = "Failed";
            $this->media->save();
            $this->fail($e);
        }//end try
    }
}
