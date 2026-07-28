<?php

namespace App\Models;

use App\Helpers;
use App\Jobs\Media\GenerateVariants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;

/**
 * @property string $url
 * @property string $thumbnail
 * @property string $file_type
 * @property bool|null $is_private
 * @property bool|null $can_delete
 */
class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'title',
        'mime_type',
        'size',
        'user_id',
        'hash',
        'storage_disk',
        'password',
        'status',
        'visibility',
        'consent_notes',
        'caption',
        'tags',
        'photographed_at',
    ];

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'name';

    /**
     * The key type for the model.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'variants' => 'array',
        'last_processing_failed_at' => 'datetime',
        'photographed_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected $appends = [
        'thumbnail',
        'file_type'
    ];

    /**
     * Media variant details.
     *
     * @var int[][][]
     */
    protected static $variants = [
        'image/*' => [
            'thumbnail' => ['width' => 250, 'height' => 250],
            'sm'     => ['width' => 300, 'height' => 225],
            'md'    => ['width' => 768, 'height' => 576],
            'lg'     => ['width' => 1024, 'height' => 768],
            'xl'    => ['width' => 1536, 'height' => 1152],
            '2xl'   => ['width' => 2048, 'height' => 1536],
            'scaled'    => ['width' => 2560, 'height' => 1920]
        ],
        'text/plain' => [
            'thumbnail' => ['width' => 250, 'height' => 250]
        ],
        'application/pdf' => [
            'thumbnail' => ['width' => 250, 'height' => 250]
        ],
        'video/*' => [
            'thumbnail' => ['width' => 250, 'height' => 250]
        ],
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function($media) {
            $hash = $media->hash;
            if(Media::where('hash', $hash)->where('storage_disk', $media->storageDiskName())->count() > 1) {
                return;
            }

            $disk = $media->sourceStorage();
            if($disk->exists($hash)) {
                $disk->delete($hash);
            }

            $media->deleteAllVariants();
        });
    }

    /**
     * Get the URL of the media.
     */
    public function getUrlAttribute(): string
    {
        return route('media.download', $this);
    }

    public function url($variant, $strict = false): string
    {
        if(!$strict) {
            $data = $this->getClosestVariant($variant);
        } else {
            $variantFile = $this->variantPath($variant);
            if($this->variants === null || !array_key_exists($variant, $this->variants) || $variantFile === null) {
                return '';
            }

            $data = [
                'variant' => $variant,
                'name' => pathinfo($this->name, PATHINFO_FILENAME) . '-' . $variant . '.' . $this->variants[$variant]['extension'],
                'mime_type' => $this->variants[$variant]['mime_type'],
                'file' => $variantFile,
            ];
        }


        $url = route('media.download', $this);

        return $url . ($data['variant'] !== '' ? '?' . $data['variant'] : '');
    }

    /**
     * Get the thumbnail of the media.
     */
    public function getThumbnailAttribute(): string
    {
        if($this->password === null || Auth::user()?->isAdmin()) {
            if ($this->hasVariant('thumbnail')) {
                $url = $this->url('thumbnail', true);
                if ($url !== '') {
                    return $url;
                }
            }
        }

        $thumbnail = '/thumbnails/' . pathinfo($this->name, PATHINFO_EXTENSION) . '.webp';

        if(file_exists(public_path($thumbnail))) {
            return asset($thumbnail);
        }

        return asset('/thumbnails/unknown.webp');
    }

    public function getFileTypeAttribute(): string
    {
        $extension = strtolower(pathinfo($this->name, PATHINFO_EXTENSION));

        if(str_starts_with($this->mime_type, 'image/')) {
            return 'Image (' . $extension . ')';
        } else if(str_starts_with($this->mime_type, 'video/')) {
            return 'Video (' . $extension . ')';
        } else if(str_starts_with($this->mime_type, 'audio/')) {
            return 'Audio (' . $extension . ')';
        } else if($this->mime_type === 'application/pdf') {
            return 'PDF Document';
        } else if($this->mime_type === 'text/plain') {
            return 'Text Document';
        } else if($extension === 'sb3') {
            return 'Scratch 3 Project';
        } else if($extension === 'stopmotionstudio' || $extension === 'stopmotionstudiomobile') {
            return 'Stop Motion Studio Project';
        }

        return 'File (' . $extension . ')';
    }

    /**
     * Get the user that owns the media.
     */
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Get all the models attached to the media.
     */
    public function mediable()
    {
        return $this->morphTo();
    }

    /**
     * @return MorphToMany<Workshop, $this>
     */
    public function workshops(): MorphToMany
    {
        return $this->morphedByMany(Workshop::class, 'mediable', 'mediables', 'media_name', 'mediable_id')
            ->withPivot('collection')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<Workshop, $this>
     */
    public function workshopPhotos(): MorphToMany
    {
        return $this->workshops()->wherePivot('collection', 'workshop_photos');
    }

    /**
     * Get the media as a temp file.
     *
     * @return string|null The temporary file path or null.
     */
    public function getAsTempFile(): string|null
    {
        if($this->hash === null) {
            return null;
        }

        $file = tempnam(sys_get_temp_dir(), 'media_');
        $disk = $this->sourceStorage();
        if($disk->exists($this->hash) === false) {
            return null;
        }

        $sourceStream = $disk->readStream($this->hash);
        if (!is_resource($sourceStream)) {
            @unlink($file);
            return null;
        }

        $targetStream = fopen($file, 'wb');
        if (!is_resource($targetStream)) {
            fclose($sourceStream);
            @unlink($file);
            return null;
        }

        // Stream data between files to avoid loading large media into PHP memory.
        $bytesCopied = stream_copy_to_stream($sourceStream, $targetStream);
        fclose($sourceStream);
        fclose($targetStream);

        if ($bytesCopied === false || $bytesCopied < 0) {
            @unlink($file);
            return null;
        }

        return $file;
    }

    /**
     * Set the media from a file.
     *
     * @param string $file The file to set.
     */
    public function storeFromTempFile(string $file): void
    {
        $stream = fopen($file, 'r+');
        if (! is_resource($stream)) {
            return;
        }

        $this->sourceStorage()->put($this->hash, $stream);
    }

    /**
     * Generate variants for this media.
     *
     * @return void
     */
    public function generateVariants(bool $overwrite = true): void
    {
        $this->status = 'queued';
        $this->save();
        dispatch(new GenerateVariants($this, $overwrite))->onQueue('media');
    }

    public function path(): string|null
    {
        $disk = $this->sourceStorage();
        if(!$disk->exists($this->hash)) {
            return null;
        }

        return $disk->path($this->hash);
    }

    /**
     * Add a variant to the media.
     *
     * @param string $name The name of the variant.
     * @param string $mime_type The mime type of the variant.
     * @param string $file The file to store.
     *
     * @return void
     */
    public function addVariant(string $name, string $mime_type, string $extension, string $file): void
    {
        $name = strtolower($name);
        $storage = $this->variantStorage();

        if (isset($this->variants[$name])) {
            if ($storage->exists($this->hash . '-' . $name)) {
                $storage->delete($this->hash . '-' . $name);
            }
        }

        $storage->putFileAs('/', $file, $this->hash . '-' . $name);

        $variants = $this->variants;
        $variants[$name] = [
             'mime_type' => $mime_type,
             'extension' => $extension
        ];
        $this->variants = $variants;

        $this->save();
    }

    /**
     * Does a variant of the media exist.
     *
     * @param string $variant The variant to check.
     *
     * @return bool True if the variant exists, false otherwise.
     */
    public function hasVariant($variant): bool
    {
        $variant = strtolower($variant);
        $storage = $this->variantStorage();

        return $storage->exists($this->hash . '-' . $variant);
    }

    /**
     * Delete a variant of the media.
     *
     * @param string $variant The variant to delete.
     *
     * @return void
     */
    public function deleteVariant($variant): void
    {
        $variant = strtolower($variant);
        $storage = $this->variantStorage();
        $variants = $this->variants ?? [];

        if(isset($variants[$variant])) {
            if($storage->exists($this->hash . '-' . $variant)) {
                $storage->delete($this->hash . '-' . $variant);
            }
        }

        unset($variants[$variant]);
        $this->variants = $variants === [] ? null : $variants;

        $this->save();
    }

    /**
     * Delete all variants of the media.
     *
     * @return void
     */
    public function deleteAllVariants(): void
    {
        $storage = $this->variantStorage();
        if($this->variants === null) {
            return;
        }

        foreach($this->variants as $variant => $file) {
            if($storage->exists($this->hash . '-' . $variant)) {
                $storage->delete($this->hash . '-' . $variant);
            }
        }

        $this->variants = null;
        $this->save();
    }

    /**
     * Get the variant types for the media.
     *
     * @param string $matchingKey The matching key.
     *
     * @return array The variant types.
     */
    /**
     * @param-out string|null $matchingKey
     * @return array<string, array<string, int>>
     */
    public function getVariantTypes(&$matchingKey = null)
    {
        $key = Helpers::findMatchingMimeTypeKey($this->mime_type, Media::$variants);
        if($key === false) {
            $matchingKey = null;
            return [];
        }

        $matchingKey = $key;
        return Media::$variants[$key];
    }

    public function getClosestVariant($key)
    {
        $variants = $this->getVariantTypes();

        if($this->variants && count($variants) > 0) {
            $found = false;
            foreach ($variants as $variant => $data) {
                if($variant === $key) {
                    $found = true;
                }

                $variantFile = $this->variantPath($variant);
                if($found && array_key_exists($variant, $this->variants) && $variantFile !== null) {
                    return [
                        'variant' => $variant,
                        'name' => pathinfo($this->name, PATHINFO_FILENAME) . '-' . $variant . '.' . $this->variants[$variant]['extension'],
                        'mime_type' => $this->variants[$variant]['mime_type'],
                        'file' => $variantFile,
                    ];
                }
            }
        }

        return [
            'variant' => null,
            'name' => $this->name,
            'mime_type' => $this->mime_type,
            'file' => $this->path()
        ];
    }

    public function variantPath(string $variant): string|null
    {
        $variant = strtolower($variant);
        $storage = $this->variantStorage();
        $key = $this->hash . '-' . $variant;

        if (! $storage->exists($key)) {
            return null;
        }

        return $storage->path($key);
    }

    public function storageDiskName(): string
    {
        $disk = trim((string) ($this->storage_disk ?? ''));

        return $disk !== '' ? $disk : 'media';
    }

    public function sourceStorage(): Filesystem
    {
        return Storage::disk($this->storageDiskName());
    }

    public function variantStorage(): Filesystem
    {
        return Storage::disk('media');
    }
}
