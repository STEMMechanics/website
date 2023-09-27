<?php

namespace App\Models;

use App\Enum\HttpResponseCodes;
use App\Jobs\MediaWorkerJob;
use App\Jobs\MoveMediaJob;
use App\Traits\Uuids;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\FFMpeg;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use SplFileInfo;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Media extends Model
{
    use HasFactory;
    use Uuids;
    use DispatchesJobs;

    public const NO_ERROR                   = 0;

    public const INVALID_FILE_ERROR         = 1;
    public const FILE_SIZE_EXCEEDED_ERROR   = 2;
    public const FILE_NAME_EXISTS_ERROR     = 3;
    public const TEMP_FILE_ERROR            = 4;

    public const STORAGE_VALID              = 0;
    public const STORAGE_MIME_MISSING       = 10;   // Mime type is missing and cannot verify
    public const STORAGE_NOT_FOUND          = 11;   // Storage name is not found
    public const STORAGE_INVALID_SECURITY   = 12;   // Security setting invalid for storage

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'user_id',
        'mime_type',
        'security',
        'storage',
        'description',
        'name',
        'size',
    ];

    /**
     * The attributes that are appended.
     *
     * @var array<string>
     */
    protected $appends = [
        'url',
    ];

    /**
     * The default attributes.
     *
     * @var string[]
     */
    protected $attributes = [
        'storage' => 'cdn',
        'variants' => '[]',
        'description' => '',
        'dimensions' => '',
        'security' => '',
        'thumbnail' => '',
    ];

    /**
     * The storage file list cache.
     *
     * @var array
     */
    protected static $storageFileListCache = [];

    /**
     * Object variant details.
     *
     * @var int[][][]
     */
    protected static $objectVariants = [
        'image' => [
            'small'     => ['width' => 300, 'height' => 225],
            'medium'    => ['width' => 768, 'height' => 576],
            'large'     => ['width' => 1024, 'height' => 768],
            'xlarge'    => ['width' => 1536, 'height' => 1152],
            'xxlarge'   => ['width' => 2048, 'height' => 1536],
            'scaled'    => ['width' => 2560, 'height' => 1920]
        ]
    ];

    /**
     * Staging file path of asset for processing.
     *
     * @var string
     */
    private $stagingFilePath = "";


    /**
     * Model Boot
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();

        $clearCache = function ($media) {
            Cache::forget("media:{$media->id}");
        };

        static::updating(function ($media) use ($clearCache) {
            $clearCache($media);
        });

        static::deleting(function ($media) use ($clearCache) {
            $clearCache($media);
            $media->deleteFile();
        });
    }

    /**
     * Get Object Variants.
     *
     * @param string $type The variant object to get.
     * @return array The variant data.
     */
    public static function getObjectVariants(string $type): array
    {
        if (isset(self::$objectVariants[$type]) === true) {
            return self::$objectVariants[$type];
        }

        return [];
    }

    /**
     * Variants Get Mutator.
     *
     * @param mixed $value The value to mutate.
     * @param bool $raw Values are not run through urlencode.
     * @return array|null The mutated value.
     */
    public function getVariantsAttribute(mixed $value, bool $raw = false): array|null
    {
        if (is_string($value) === true) {
            $decodedValue = json_decode($value, true);

            // Check if the decoded value is an array
            if ($raw == false && is_array($decodedValue)) {
                // Loop through the array and encode each value
                foreach ($decodedValue as &$item) {
                    if (is_string($item)) {
                        $item = rawurlencode($item);
                    }
                }
            }
    
            return $decodedValue;
        }

        return [];
    }

    /**
     * Variants Set Mutator.
     *
     * @param mixed $value The value to mutate.
     * @return void
     */
    public function setVariantsAttribute(mixed $value): void
    {
        if (is_array($value) !== true) {
            $value = [];
        }

        $this->attributes['variants'] = json_encode(($value ?? []));
    }

    /**
     * Get previous variant.
     *
     * @param string $type    The variant type.
     * @param string $variant The initial variant.
     * @return string The previous variant name (or '').
     */
    public function getPreviousVariant(string $type, string $variant): string
    {
        if (isset(self::$objectVariants[$type]) === false) {
            return '';
        }

        $variants = self::$objectVariants[$type];
        $keys = array_keys($variants);

        $currentIndex = array_search($variant, $keys);
        if ($currentIndex === false || $currentIndex === 0) {
            return '';
        }

        return $keys[($currentIndex - 1)];
    }

    /**
     * Get next variant.
     *
     * @param string $type    The variant type.
     * @param string $variant The initial variant.
     * @return string The next variant name (or '').
     */
    public function getNextVariant(string $type, string $variant): string
    {
        if (isset(self::$objectVariants[$type]) === false) {
            return '';
        }

        $variants = self::$objectVariants[$type];
        $keys = array_keys($variants);

        $currentIndex = array_search($variant, $keys);
        if ($currentIndex === false || $currentIndex === (count($keys) - 1)) {
            return '';
        }

        return $keys[($currentIndex + 1)];
    }

    /**
     * Get variant URL.
     *
     * @param string  $variant       The variant to find.
     * @param boolean $returnNearest Return the nearest variant if request is not found.
     * @return string The URL.
     */
    public function getVariantURL(string $variant, bool $returnNearest = true): string
    {
        $variants = $this->variants;
        if (isset($variants[$variant]) === true) {
            return self::getUrlPath() . $variants[$variant];
        }

        if ($returnNearest === true) {
            $variantType = explode('/', $this->mime_type)[0];
            $previousVariant = $variant;
            while (empty($previousVariant) === false) {
                $previousVariant = $this->getPreviousVariant($variantType, $previousVariant);
                if (empty($previousVariant) === false && isset($variants[$previousVariant]) === true) {
                    return self::getUrlPath() . $variants[$previousVariant];
                }
            }
        }

        return '';
    }

    /**
     * Delete file and associated files with the modal.
     *
     * @return void
     */
    public function deleteFile(): void
    {
        if (strlen($this->storage) > 0 && strlen($this->name) > 0 && Storage::disk($this->storage)->exists($this->name) === true) {
            Storage::disk($this->storage)->delete($this->name);
        }

        $this->deleteThumbnail();
        $this->deleteVariants();
        $this->invalidateCFCache();
    }

    /**
     * Invalidate Cloudflare Cache.
     *
     * @return void
     */
    private function invalidateCFCache(): void
    {
        $zone_id = env("CLOUDFLARE_ZONE_ID");
        $api_key = env("CLOUDFLARE_API_KEY");
        if ($zone_id !== null && $api_key !== null && $this->url !== "") {
            $urls = [$this->url];

            foreach ($this->variants as $variant => $name) {
                $urls[] = str_replace($this->name, $name, $this->url);
            }

            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/" . $zone_id . "/purge_cache",
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => "DELETE",
                CURLOPT_POSTFIELDS => json_encode(["files" => $urls]),
                CURLOPT_HTTPHEADER => [
                    "Content-Type: application/json",
                    "Authorization: Bearer " . $api_key
                ],
            ]);
            curl_exec($curl);
            curl_close($curl);
        }//end if
    }

    /**
     * Get URL path
     *
     * @return string
     */
    public function getUrlPath(): string
    {
        $url = config("filesystems.disks.$this->storage.url");
        return "$url/";
    }

    /**
     * Return the file URL
     *
     * @return string
     */
    public function getUrlAttribute(): string
    {
        if (isset($this->attributes['name']) === true) {
            return self::getUrlPath() . $this->name;
        }

        return '';
    }

    /**
     * Return the file owner
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Transform the media through the Media Job Queue
     *
     * @param array $transform The transform data.
     * @return MediaJob
     */
    public function transform(array $transform): MediaJob
    {
        $mediaJob = new MediaJob([
            'media_id' => $this->media,
            'user_id' => auth()->user()?->id,
            'data' => json_encode(['transform' => $transform]),
        ]);

        try {
            MediaWorkerJob::dispatch($mediaJob)->onQueue('media');
            return $mediaJob;
        } catch (\Exception $e) {
            $this->error('Failed to transform media');
            throw $e;
        }//end try

        return null;
    }

    /**
     * Download the file from the storage to the user.
     *
     * @param string  $variant  The variant to download or null if none.
     * @param boolean $fallback Fallback to the original file if the variant is not found.
     * @return JsonResponse|StreamedResponse The response.
     * @throws BindingResolutionException The Exception.
     */
    public function download(string $variant = null, bool $fallback = true)
    {
        $path = $this->name;
        if ($variant !== null) {
            if (array_key_exists($variant, $this->variant) === true) {
                $path = $this->variant[$variant];
            } else {
                return response()->json(
                    ['message' => 'The resource was not found.'],
                    HttpResponseCodes::HTTP_NOT_FOUND
                );
            }
        }

        $disk = Storage::disk($this->storage);
        if ($disk->exists($path) === true) {
            $stream = $disk->readStream($path);
            $response = response()->stream(
                function () use ($stream) {
                    fpassthru($stream);
                },
                200,
                [
                    'Content-Type' => $this->mime_type,
                    'Content-Length' => $disk->size($path),
                    'Content-Disposition' => 'attachment; filename="' . basename($path) . '"',
                ]
            );

            return $response;
        }

        return response()->json(['message' => 'The resource was not found.'], HttpResponseCodes::HTTP_NOT_FOUND);
    }

    /**
     * Get the server maximum upload size
     *
     * @return integer
     */
    public static function getMaxUploadSize(): int
    {
        $sizes = [
            ini_get('upload_max_filesize'),
            ini_get('post_max_size'),
            ini_get('memory_limit')
        ];

        foreach ($sizes as &$size) {
            $size = trim($size);
            $last = strtolower($size[(strlen($size) - 1)]);
            switch ($last) {
                case 'g':
                    $size = (intval($size) * 1024);
                    // Size is in MB - fallthrough
                case 'm':
                    $size = (intval($size) * 1024);
                    // Size is in KB - fallthrough
                case 'k':
                    $size = (intval($size) * 1024);
                    // Size is in B - fallthrough
            }
        }

        return min($sizes);
    }

    /**
     * Generate a file name that is available within storage.
     *
     * @param string $fileName The proposed file name.
     * @return string|boolean The available file name or false if failed.
     */
    public static function generateUniqueFileName(string $fileName)
    {
        $index = 1;
        $maxTries = 100;
        $extension = pathinfo($fileName, PATHINFO_EXTENSION);
        $fileName = static::sanitizeFilename(pathinfo($fileName, PATHINFO_FILENAME));

        if (
            static::fileNameHasSuffix($fileName) === true ||
            static::fileExistsInStorage("$fileName.$extension") === true //||
            // Media::where('name', "$fileName.$extension")->where('status', 'not like', 'failed%')->exists() === true
        ) {
            $fileName .= '-';
            for ($i = 1; $i < $maxTries; $i++) {
                $fileNameIndex = $fileName . $index;
                if (
                    static::fileExistsInStorage("$fileNameIndex.$extension") !== true &&
                    Media::where('name', "$fileNameIndex.$extension")
                        // ->where('status', 'not like', 'Failed%')
                        ->exists() !== true
                ) {
                    return "$fileNameIndex.$extension";
                }

                ++$index;
            }

            return false;
        }

        return "$fileName.$extension";
    }

    /**
     * Determines if the file name exists in any of the storage disks.
     *
     * @param string  $fileName    The file name to check.
     * @param boolean $ignoreCache Ignore the file list cache.
     * @return boolean If the file exists on any storage disks.
     */
    public static function fileExistsInStorage(string $fileName, bool $ignoreCache = false): bool
    {
        $disks = array_keys(Config::get('filesystems.disks'));

        if ($ignoreCache === false) {
            if (count(static::$storageFileListCache) === 0) {
                $disks = array_keys(Config::get('filesystems.disks'));

                foreach ($disks as $disk) {
                    try {
                        static::$storageFileListCache[$disk] = Storage::disk($disk)->allFiles();
                    } catch (\Exception $e) {
                        Log::error("Cannot get a file list for storage device '$disk'. Error: " . $e->getMessage());
                        continue;
                    }
                }
            }

            foreach (static::$storageFileListCache as $disk => $files) {
                if (in_array($fileName, $files) === true) {
                    return true;
                }
            }
        } else {
            $disks = array_keys(Config::get('filesystems.disks'));

            foreach ($disks as $disk) {
                try {
                    if (Storage::disk($disk)->exists($fileName) === true) {
                        return true;
                    }
                } catch (\Exception $e) {
                    Log::error($e->getMessage());
                    throw new \Exception("Cannot verify if file '$fileName' already exists in storage device '$disk'");
                }
            }
        }//end if

        return false;
    }

    /**
     * Test if the file name contains a special suffix.
     *
     * @param string $fileName The file name to test.
     * @return boolean If the file name contains the special suffix.
     */
    public static function fileNameHasSuffix(string $fileName): bool
    {
        $suffix = '/(-\d+x\d+|-scaled|-thumb)$/i';
        $fileNameWithoutExtension = pathinfo($fileName, PATHINFO_FILENAME);

        return preg_match($suffix, $fileNameWithoutExtension) === 1;
    }

    /**
     * Sanitize fileName for upload
     *
     * @param string $fileName Filename to sanitize.
     * @return string
     */
    private static function sanitizeFilename(string $fileName): string
    {
        /*
        # file system reserved https://en.wikipedia.org/wiki/Filename#Reserved_characters_and_words
        [<>:"/\\\|?*]|

        # control characters http://msdn.microsoft.com/en-us/library/windows/desktop/aa365247%28v=vs.85%29.aspx
        [\x00-\x1F]|

        # non-printing characters DEL, NO-BREAK SPACE, SOFT HYPHEN
        [\x7F\xA0\xAD]|

        # URI reserved https://www.rfc-editor.org/rfc/rfc3986#section-2.2
        [#\[\]@!$&\'()+,;=]|

        # URL unsafe characters https://www.ietf.org/rfc/rfc1738.txt
        [{}^\~`]
        */

        $fileName = preg_replace(
            '~
            [<>:"/\\\|?*]|
            [\x00-\x1F]|
            [\x7F\xA0\xAD]|
            [#\[\]@!$&\'()+,;=]|
            [{}^\~`]
            ~x',
            '-',
            $fileName
        );

        $fileName = ltrim($fileName, '.-');

        $fileName = preg_replace([
        // "file   name.zip" becomes "file-name.zip"
            '/ +/',
        // "file___name.zip" becomes "file-name.zip"
            '/_+/',
        // "file---name.zip" becomes "file-name.zip"
            '/-+/'
        ], '-', $fileName);
        $fileName = preg_replace([
        // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
        // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
        ], '.', $fileName);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $fileName = mb_strtolower($fileName, mb_detect_encoding($fileName));
        // ".file-name.-" becomes "file-name"
        $fileName = trim($fileName, '.-');

        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $fileName = mb_strcut(
            pathinfo($fileName, PATHINFO_FILENAME),
            0,
            (255 - ($ext !== '' ? strlen($ext) + 1 : 0)),
            mb_detect_encoding($fileName)
        ) . ($ext !== '' ? '.' . $ext : '');
        return $fileName;
    }

    /**
     * Get the Staging File path.
     *
     * @param boolean $create Create staging file if doesn't exist.
     * @return string
     */
    public function getStagingFilePath(bool $create = true): string
    {
        if ($this->stagingFilePath === "" && $create === true) {
            $this->createStagingFile();
        }

        return $this->stagingFilePath;
    }

    /**
     * Set the Staging File for processing.
     *
     * @param string  $path      The path if the new staging file.
     * @param boolean $overwrite Overwrite existing file.
     * @return void
     */
    public function setStagingFile(string $path, bool $overwrite = false): void
    {
        if ($this->stagingFilePath !== "") {
            if ($overwrite === true) {
                unlink($this->stagingFilePath);
            } else {
                // ignore request
                return;
            }
        }

        $this->stagingFilePath = $path;
    }

    /**
     * Download temporary copy of the storage file for staging.
     *
     * @return boolean If download was successful.
     */
    public function createStagingFile(): bool
    {
        if ($this->stagingFilePath === "") {
            $readStream = Storage::disk($this->storage)->readStream($this->name);
            $filePath = generateTempFilePath(pathinfo($this->name, PATHINFO_EXTENSION));

            $writeStream = fopen($filePath, 'w');
            while (feof($readStream) !== true) {
                fwrite($writeStream, fread($readStream, 8192));
            }
            fclose($readStream);
            fclose($writeStream);

            $this->stagingFilePath = $filePath;
        }//end if

        return $this->stagingFilePath !== "";
    }

    /**
     * Save the Staging File to storage
     *
     * @param boolean $delete Delete the existing staging file.
     * @param boolean $silent Update the status field with the progress.
     * @return void
     */
    public function saveStagingFile(bool $delete = true, bool $silent = false): void
    {
        if ($this->stagingFilePath !== '') {
            if (strlen($this->storage) > 0 && strlen($this->name) > 0) {
                if (Storage::disk($this->storage)->exists($this->name) === true) {
                    Storage::disk($this->storage)->delete($this->name);
                }

                /** @var Illuminate\Filesystem\FilesystemAdapter */
                $fileSystem = Storage::disk($this->storage);
                $fileSystem->putFileAs('/', $this->stagingFilePath, $this->name);
            }

            $this->generateThumbnail();
            $this->generateVariants();

            if ($delete === true) {
                $this->deleteStagingFile();
            }
        }//end if
    }

    /**
     * Clean up temporary file.
     *
     * @return void
     */
    public function deleteStagingFile(): void
    {
        if ($this->stagingFilePath !== "") {
            unlink($this->stagingFilePath);
            $this->stagingFilePath = "";
        }
    }

    /**
     * Change staging file, removing the old file if present
     *
     * @param string $newFile The new staging file.
     * @return void
     */
    public function changeStagingFile(string $newFile): void
    {
        if ($this->stagingFilePath !== "") {
            unlink($this->stagingFilePath);
        }

        $this->stagingFilePath = $newFile;
    }

    /**
     * Is a staging file present
     *
     * @return boolean
     */
    public function hasStagingFile(): bool
    {
        return $this->stagingFilePath !== "";
    }

    /**
     * Generate a Thumbnail for this media.
     *
     * @return boolean If generation was successful.
     */
    public function generateThumbnail(): bool
    {
        $thumbnailWidth = 200;
        $thumbnailHeight = 200;

        // delete existing thumbnail
        if (strlen($this->thumbnail) !== 0) {
            $path = substr($this->thumbnail, strlen($this->getUrlPath()));
            if (strlen($path) > 0 && Storage::disk($this->storage)->exists($path) === true) {
                Storage::disk($this->storage)->delete($path);
            }
        }

        $filePath = $this->getStagingFilePath();

        $fileExtension = File::extension($this->name);
        $tempImagePath = tempnam(sys_get_temp_dir(), 'thumb');
        $newFilename = pathinfo($this->name, PATHINFO_FILENAME) . "-" . uniqid() . "-thumb.webp";
        $success = false;

        if ($this->security === '') {
            if (strpos($this->mime_type, 'image/') === 0) {
                $image = Image::make($filePath);
                $image->orientate();
                $image->resize($thumbnailWidth, $thumbnailHeight, function ($constraint) {
                    $constraint->aspectRatio();
                });
                $image->fit($thumbnailWidth, $thumbnailHeight);
                $image->encode('webp', 75)->save($tempImagePath);
                $success = true;
            } elseif ($this->mime_type === 'application/pdf' && extension_loaded('imagick') === true) {
                $pdfPreview = new \Imagick();
                $pdfPreview->setResolution(300, 300);
                $pdfPreview->readImage($filePath . '[0]');
                $pdfPreview->setImageFormat('webp');
                $pdfPreview->thumbnailImage($thumbnailWidth, $thumbnailHeight, true);
                file_put_contents($tempImagePath, $pdfPreview);

                $success = true;
            } elseif ($this->mime_type === 'text/plain') {
                $image = Image::canvas($thumbnailWidth, $thumbnailHeight, '#FFFFFF');

                // Read the first few lines of the text file
                $numLines = 5;
                $text = file_get_contents($filePath);
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

                $image->encode('webp', 75)->save($tempImagePath);

                $success = true;
            } elseif (strpos($this->mime_type, 'video/') === 0) {
                $tempImagePath .= '.webp';

                try {
                    $ffmpeg = FFMpeg::create();
                    $video = $ffmpeg->open($filePath);
                    $frame = $video->frame(TimeCode::fromSeconds(5));
                    $frame->save($tempImagePath);
                } catch (\Exception $e) {
                    Log::error($e);
                }

                $success = true;
            }//end if
        }//end if

        if ($success === true && file_exists($tempImagePath) === true) {
            /** @var Illuminate\Filesystem\FilesystemAdapter */
            $fileSystem = Storage::disk($this->storage);
            $fileSystem->putFileAs('/', new SplFileInfo($tempImagePath), $newFilename);
            unlink($tempImagePath);

            $this->thumbnail = $this->getUrlPath() . $newFilename;
        } else {
            $iconExtension = 'unknown';
            if ($fileExtension !== '') {
                $iconPath = public_path('assets/fileicons/' . $fileExtension . '.webp');
                if (file_exists($iconPath) === true) {
                    $iconExtension = $fileExtension;
                }
            }

            $this->thumbnail = asset('/assets/fileicons/' . $iconExtension . '.webp');
        }

        return $success;
    }

    /**
     * Delete Media Thumbnail from storage.
     *
     * @return void
     */
    public function deleteThumbnail(): void
    {
        if (strlen($this->thumbnail) > 0) {
            $path = substr($this->thumbnail, strlen($this->getUrlPath()));

            if (strlen($path) > 0 && Storage::disk($this->storage)->exists($path) === true) {
                Storage::disk($this->storage)->delete($path);
                $this->thumbnail = ''; // Clear the thumbnail property
            }
        }
    }

    /**
     * Generate variants for this media.
     *
     * @return void
     */
    public function generateVariants(): void
    {
        // delete existing variants
        if (is_array($this->variants) === true) {
            foreach ($this->variants as $variantName => $variantFile) {
                if (Storage::disk($this->storage)->exists($variantFile) === true) {
                    Storage::disk($this->storage)->delete($variantFile);
                }
            }
        }
        $this->variants = [];

        if ($this->security === '') {
            if (strpos($this->mime_type, 'image/') === 0) {
                // Generate additional image sizes
                $sizes = Media::getObjectVariants('image');

                // download original from CDN if no local file
                $filePath = $this->getStagingFilePath();

                $originalImage = Image::make($filePath);

                $imageSize = $originalImage->getSize();
                $isPortrait = $imageSize->getHeight() > $imageSize->getWidth();

                // Swap width and height values for portrait images
                foreach ($sizes as $variantName => &$size) {
                    if ($isPortrait === true) {
                        $temp = $size['width'];
                        $size['width'] = $size['height'];
                        $size['height'] = $temp;
                    }
                }

                $dimensions = [$originalImage->getWidth(), $originalImage->getHeight()];
                $this->dimensions = implode('x', $dimensions);

                foreach ($sizes as $variantName => $size) {
                    $postfix = "{$size['width']}x{$size['height']}";
                    if ($variantName === 'scaled') {
                        $postfix = 'scaled';
                    }

                    $newFilename = pathinfo($this->name, PATHINFO_FILENAME) . "-" . uniqid() . "-$postfix.webp";

                    // Get the largest available variant
                    if ($dimensions[0] >= $size['width'] && $dimensions[1] >= $size['height']) {
                        // Store the variant in the variants array
                        $variants[$variantName] = $newFilename;

                        // Resize the image to the variant size if its dimensions are greater than the
                        // specified size
                        $image = clone $originalImage;

                        $imageSize = $image->getSize();
                        if ($imageSize->getWidth() > $size['width'] || $imageSize->getHeight() > $size['height']) {
                            $image->resize($size['width'], $size['height'], function ($constraint) {
                                $constraint->aspectRatio();
                                $constraint->upsize();
                            });
                            $image->resizeCanvas($size['width'], $size['height'], 'center', false, 'rgba(0,0,0,0)');
                        }

                        $image->orientate();

                        // Optimize and store image
                        $tempImagePath = tempnam(sys_get_temp_dir(), 'optimize');
                        $image->encode('webp', 75)->save($tempImagePath);
                        /** @var Illuminate\Filesystem\FilesystemAdapter */
                        $fileSystem = Storage::disk($this->storage);
                        $fileSystem->putFileAs('/', new SplFileInfo($tempImagePath), $newFilename);
                        unlink($tempImagePath);
                    }//end if
                }//end foreach

                // Set missing variants to the largest available variant
                foreach ($sizes as $variantName => $size) {
                    if (isset($variants[$variantName]) === false) {
                        $variants[$variantName] = $this->name;
                    }
                }

                $this->variants = $variants;
            }//end if
        }//end if
    }

    /**
     * Delete the Media variants from storage.
     *
     * @return void
     */
    public function deleteVariants(): void
    {
        if (strlen($this->name) > 0 && strlen($this->storage) > 0) {
            foreach ($this->variants as $variantName => $fileName) {
                Storage::disk($this->storage)->delete($fileName);
            }

            $this->variants = [];
        }
    }

    /**
     * Set Media status to OK
     *
     * @return void
     */
    public function ok(): void
    {
        // $this->status = "OK";
        $this->save();
    }

    /**
     * Set Media status to an error
     * @param string $error The error to set.
     * @return void
     */
    public function error(string $error = ""): void
    {
        // $this->status = "Error" . ($error !== "" ? ": {$error}" : "");
        $this->save();
    }

    /**
     * Set Media status
     * @param string $status The status to set.
     * @return void
     */
    public function status(string $status = ""): void
    {
        // $this->status = "Info: " . $status;
        $this->save();
    }

    public function jobs(): HasMany {
        return $this->hasMany(MediaJob::class, 'media_id');
    }

    public static function verifyStorage($mime_type, $security, &$storage): int {
        if($mime_type === '') {
            return Media::STORAGE_MIME_MISSING;
        }

        if($storage === '') {
            if($security === '') {
                if (strpos($mime_type, 'image/') === 0) {
                    $storage = 'local';
                } else {
                    $storage = 'cdn';
                }
            } else {
                $storage = 'private';
            }
        } else {
            if(Storage::has($storage) === false) {
                return Media::STORAGE_NOT_FOUND;
            }

            if(strcasecmp($storage, 'private') !== 0) {
                return Media::STORAGE_INVALID_SECURITY;
            }
        }

        return Media::STORAGE_VALID;
    }
}
