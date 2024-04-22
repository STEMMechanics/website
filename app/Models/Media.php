<?php

namespace App\Models;

use App\Helpers;
use App\Jobs\Media\GenerateVariants;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'title',
        'mime_type',
        'size',
        'user_id',
        'hash'
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
     * @var array
     */
    protected $casts = [
        'variants' => 'array'
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected $appends = [
        'url',
        'thumbnail'
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
        ]
    ];

    public static function boot()
    {
        parent::boot();

        static::deleting(function($media) {
            $hash = $media->hash;
            if(Media::where('hash', $hash)->count() > 1) {
                return;
            }

            $disk = Storage::disk('media');
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
        return Storage::disk('media')->url($this->name);
    }

    public function url($variant, $strict = false): string
    {
        if(!$strict) {
            $data = $this->getClosestVariant($variant);
        } else {
            if($this->variants === null || !array_key_exists($variant, $this->variants)) {
                return '';
            }

            $data = [
                'variant' => $variant,
                'name' => pathinfo($this->name, PATHINFO_FILENAME) . '-' . $variant . '.' . $this->variants[$variant]['extension'],
                'mime_type' => $this->variants[$variant]['mime_type'],
                'file' => $this->path() . '-' . $variant
            ];
        }


        return Storage::disk('media')->url($this->name) . ($data['variant'] !== '' ? '?' . $data['variant'] : '');
    }

    /**
     * Get the thumbnail of the media.
     */
    public function getThumbnailAttribute(): string
    {
        $url = $this->url('thumbnail', true);
        if($url !== '') {
            return $url;
        }

        $thumbnail = '/thumbnails/' . pathinfo($this->name, PATHINFO_EXTENSION) . '.webp';

        if(file_exists(public_path($thumbnail))) {
            return asset($thumbnail);
        }

        return asset('/thumbnails/unknown.webp');
    }

    /**
     * Get the user that owns the media.
     */
    public function user()
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
        $disk = Storage::disk('media');
        if($disk->exists($this->hash) === false) {
            return null;
        }

        $stream = $disk->getDriver()->readStream($this->hash);
        is_resource($stream) && file_put_contents($file, stream_get_contents($stream), FILE_APPEND);
        return $file;
    }

    /**
     * Set the media from a file.
     *
     * @param string $file The file to set.
     */
    public function storeFromTempFile(string $file): void
    {
        Storage::disk('media')->put($this->name, fopen($file, 'r+'));
    }

    /**
     * Generate variants for this media.
     *
     * @return void
     */
    public function generateVariants(bool $overwrite = true): void
    {
        dispatch(new GenerateVariants($this, $overwrite))->onQueue('media');
    }

    public function path(): string|null
    {
        $disk = Storage::disk('media');
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
        $storage = Storage::disk('media');

        if (isset($this->variants[$name])) {
            if ($storage->exists($this->hash . '-' . $name)) {
                $storage->delete($this->hash . '-_' . $name);
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
        $storage = Storage::disk('media');

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
        $storage = Storage::disk('media');

        if(isset($this->variants[$variant])) {
            if($storage->exists($this->hash . '-' . $variant)) {
                $storage->delete($this->hash . '-' . $variant);
            }
        }

        unset($this->variants[$variant]);

        $this->save();
    }

    /**
     * Delete all variants of the media.
     *
     * @return void
     */
    public function deleteAllVariants(): void
    {
        $storage = Storage::disk('media');
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

                if($found && array_key_exists($variant, $this->variants)) {
                    return [
                        'variant' => $variant,
                        'name' => pathinfo($this->name, PATHINFO_FILENAME) . '-' . $variant . '.' . $this->variants[$variant]['extension'],
                        'mime_type' => $this->variants[$variant]['mime_type'],
                        'file' => $this->path() . '-' . $variant
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
}
