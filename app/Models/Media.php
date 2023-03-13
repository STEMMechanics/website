<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Facades\Image;
use Spatie\ImageOptimizer\OptimizerChainFactory;

class Media extends Model
{
    use HasFactory;
    use Uuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'name',
        'mime',
        'user_id',
        'size',
        'permission'
    ];

    /**
     * The attributes that are hidden.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'path',
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
     * Model Boot
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::updating(function ($media) {
            if (array_key_exists('permission', $media->getChanges()) === true) {
                $origPermission = $media->getOriginal()['permission'];
                $newPermission = $media->permission;

                $origPath = Storage::disk(Media::getStorageId(empty($origPermission)))->path($media->name);
                $newPath = Storage::disk(Media::getStorageId(empty($newPermission)))->path($media->name);

                if ($origPath !== $newPath) {
                    if (file_exists($origPath) === true) {
                        if (file_exists($newPath) === true) {
                            $fileParts = pathinfo($newPath);
                            $newName = '';

                            // need a new name!
                            $tmpPath = $newPath;
                            while (file_exists($tmpPath) === true) {
                                $newName = uniqid('', true) . $fileParts['extension'];
                                $tmpPath = $fileParts['dirname'] . '/' . $newName;
                            }

                            $media->name = $newName;
                        }

                        rename($origPath, $newPath);
                    }//end if
                }//end if
            }//end if
        });
    }

    /**
     * Return the file URL
     *
     * @return string
     */
    public function getUrlAttribute()
    {
        $url = config('filesystems.disks.' . Media::getStorageId($this) . '.url');
        if (empty($url) === false) {
            $replace = [
                'id'    => $this->id,
                'name'  => $this->name
            ];

            $url = str_ireplace(array_map(function ($item) {
                return '%' . $item . '%';
            }, array_keys($replace)), array_values($replace), $url);

            return $url;
        }//end if

        return '';
    }

    /**
     * Return the file owner
     *
     * @return BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the file full local path
     *
     * @return string
     */
    public function path()
    {
        return Storage::disk(Media::getStorageId($this))->path($this->name);
    }

    /**
     * Get Storage ID
     *
     * @param mixed $mediaOrPublic Media object or if file is public.
     * @return string
     */
    public static function getStorageId(mixed $mediaOrPublic)
    {
        $isPublic = true;

        if ($mediaOrPublic instanceof Media) {
            $isPublic = empty($mediaOrPublic->permission);
        } else {
            $isPublic = boolval($mediaOrPublic);
        }

        return $isPublic === true ? 'public' : 'local';
    }

    /**
     * Place uploaded file into storage. Return full path or null
     *
     * @param UploadedFile $file   File to put into storage.
     * @param boolean      $public Is the file available to the public.
     * @return array|null
     */
    public static function store(UploadedFile $file, bool $public = true)
    {
        $storage = Media::getStorageId($public);
        $name = $file->store('', ['disk' => $storage]);

        if ($name === false) {
            return null;
        }

        $path = Storage::disk($storage)->path($name);

        // Generate additional image sizes
        $sizes = [
            'thumb' => [150, 150],
            'small' => [300, 300],
            'medium' => [640, 640],
            'large' => [1024, 1024],
            'xlarge' => [1536, 1536],
        ];
        $images = ['full' => $path];
        foreach ($sizes as $sizeName => $size) {
            $image = Image::make($path);
            $image->resize($size[0], $size[1], function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            });
            $newPath = pathinfo($path, PATHINFO_DIRNAME) . '/' . pathinfo($path, PATHINFO_FILENAME) . "-$sizeName." . pathinfo($path, PATHINFO_EXTENSION);
            $image->save($newPath);
            $images[$sizeName] = $newPath;
        }

        // Optimize all images
        $optimizerChain = OptimizerChainFactory::create();
        foreach ($images as $imagePath) {
            $optimizerChain->optimize($imagePath);
        }

        return [
            'name' => $name,
            'path' => $path
        ];
    }

    /**
     * Get the server maximum upload size
     *
     * @return integer
     */
    public static function maxUploadSize()
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
     * Sanitize filename for upload
     *
     * @param string $filename Filename to sanitize.
     * @return string
     */
    public static function sanitizeFilename(string $filename)
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

        $filename = preg_replace(
            '~
            [<>:"/\\\|?*]|
            [\x00-\x1F]|
            [\x7F\xA0\xAD]|
            [#\[\]@!$&\'()+,;=]|
            [{}^\~`]
            ~x',
            '-',
            $filename
        );

        $filename = ltrim($filename, '.-');

        $filename = preg_replace([
            // "file   name.zip" becomes "file-name.zip"
            '/ +/',
            // "file___name.zip" becomes "file-name.zip"
            '/_+/',
            // "file---name.zip" becomes "file-name.zip"
            '/-+/'
        ], '-', $filename);
        $filename = preg_replace([
            // "file--.--.-.--name.zip" becomes "file.name.zip"
            '/-*\.-*/',
            // "file...name..zip" becomes "file.name.zip"
            '/\.{2,}/'
        ], '.', $filename);
        // lowercase for windows/unix interoperability http://support.microsoft.com/kb/100625
        $filename = mb_strtolower($filename, mb_detect_encoding($filename));
        // ".file-name.-" becomes "file-name"
        $filename = trim($filename, '.-');

        $ext = pathinfo($filename, PATHINFO_EXTENSION);
        $filename = mb_strcut(
            pathinfo($filename, PATHINFO_FILENAME),
            0,
            (255 - ($ext !== '' ? strlen($ext) + 1 : 0)),
            mb_detect_encoding($filename)
        ) . ($ext !== '' ? '.' . $ext : '');
        return $filename;
    }
}
