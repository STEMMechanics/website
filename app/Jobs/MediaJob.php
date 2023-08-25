<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use FFMpeg;
use FFMpeg\Coordinate\Dimension;
use Intervention\Image\Facades\Image;

class MediaJob implements ShouldQueue
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
     * Actions should be silent (not update the status field)
     *
     * @var boolean
     */
    protected $silent;

    /**
     * Actions to make on the Media
     *
     * @var array
     */
    protected $actions;


    /**
     * Create a new job instance.
     *
     * @param Media   $media   The media model.
     * @param array   $actions The media actions to make.
     * @param boolean $silent  Update the media status with progress.
     * @return void
     */
    public function __construct(Media $media, array $actions, bool $silent = false)
    {
        $this->media = $media;
        $this->silent = $silent;
        $this->actions = $actions;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            // FILE
            if (array_key_exists("file", $this->actions) === true) {
                $uploadData = $this->actions["file"];

                if (array_key_exists("path", $uploadData) === false || file_exists($uploadData["path"]) === false) {
                    $this->media->error("Upload file does not exist");
                    return;
                }

                $filePath = $uploadData["path"];

                // convert HEIC files to JPG
                $fileExtension = File::extension($filePath);
                if ($fileExtension === 'heic') {
                    if ($this->silent === false) {
                        $this->media->status('Converting image');
                    }

                    // Get the path without the file name
                    $uploadedFileDirectory = dirname($filePath);

                    // Convert the HEIC file to JPG
                    $jpgFileName = pathinfo($filePath, PATHINFO_FILENAME) . '.jpg';
                    $jpgFilePath = $uploadedFileDirectory . '/' . $jpgFileName;
                    if (file_exists($jpgFilePath) === true) {
                        $this->media->error("File already exists in storage");
                        return;
                    }

                    Image::make($filePath)->save($jpgFilePath);

                    // Update the uploaded file path and file name
                    unlink($filePath);
                    $filePath = $jpgFilePath;
                    $this->media->name = $jpgFileName;
                    $this->media->save();
                }//end if

                // Check if file already exists
                if (Storage::disk($this->media->storage)->exists($this->media->name) === true) {
                    if (array_key_exists('replace', $uploadData) === false || isTrue($uploadData['replace']) === false) {
                        $this->media->error("File already exists in storage");
                        $errorStr = "cannot upload file {$this->media->storage} " . // phpcs:ignore
                        "/ {$this->media->name} as it already exists";
                        Log::info($errorStr);
                        throw new \Exception($errorStr);
                    }
                }

                $this->media->setStagingFile($filePath);
            }//end if

            $this->media->createStagingFile();
            $this->media->deleteFile();

            // Modifications
            if (strpos($this->media->mime_type, 'image/') === 0) {
                $modified = false;
                $image = Image::make($this->media->getStagingFilePath());

                // ROTATE
                if (array_key_exists("rotate", $this->actions) === true) {
                    $rotate = intval($this->actions["rotate"]);
                    if ($rotate !== 0) {
                        if ($this->silent === false) {
                            $this->media->status('Rotating image');
                        }
                        $image = $image->rotate($rotate);
                        $modified = true;
                    }
                }

                // FLIP-H/V
                if (array_key_exists('flip', $this->actions) === true) {
                    if (stripos($this->actions['flip'], 'h') !== false) {
                        if ($this->silent === false) {
                            $this->media->status('Flipping image');
                        }
                        $image = $image->flip('h');
                        $modified = true;
                    }

                    if (stripos($this->actions['flip'], 'v') !== false) {
                        if ($this->silent === false) {
                            $this->media->status('Flipping image');
                        }
                        $image = $image->flip('v');
                        $modified = true;
                    }
                }

                // CROP
                if (array_key_exists("crop", $this->actions) === true) {
                    $cropData = $this->actions["crop"];
                    $width = intval(arrayDefaultValue("width", $cropData, $image->getWidth()));
                    $height = intval(arrayDefaultValue("height", $cropData, $image->getHeight()));
                    $x = intval(arrayDefaultValue("x", $cropData, 0));
                    $y = intval(arrayDefaultValue("y", $cropData, 0));

                    if ($this->silent === false) {
                        $this->media->status('Cropping image');
                    }
                    $image = $image->crop($width, $height, $x, $y);
                    $modified = true;
                }//end if

                if ($modified === true) {
                    $image->save();
                }
            } elseif (strpos($this->media->mime_type, 'video/') === 0) {
                $ffmpeg = FFMpeg\FFMpeg::create();
                $video = $ffmpeg->open($this->media->getStagingFilePath());

                /** @var FFMpeg\Media\Video::filters */
                $filters = $video->filters();

                // ROTATE
                if (array_key_exists("rotate", $this->actions) === true) {
                    $rotate = intval($this->actions["rotate"]);
                    $rotate = (($rotate % 360 + 360) % 360); // remove excess rotations
                    $rotate = (round($rotate / 90) * 90); // round to nearest 90%

                    if ($rotate > 0) {
                        if ($this->silent === false) {
                            $this->media->status('Rotating video');
                        }

                        if ($rotate === 90) {
                            $filters->rotate(FFMpeg\Filters\Video\RotateFilter::ROTATE_90);
                        } elseif ($rotate === 190) {
                            $filters->rotate(FFMpeg\Filters\Video\RotateFilter::ROTATE_180);
                        } elseif ($rotate === 270) {
                            $filters->rotate(FFMpeg\Filters\Video\RotateFilter::ROTATE_270);
                        }
                    }
                }

                // FLIP-H/V
                if (array_key_exists('flip', $this->actions) === true) {
                    if (stripos($this->actions['flip'], 'h') !== false) {
                        if ($this->silent === false) {
                            $this->media->status('Flipping video');
                        }
                        $filters->hflip()->synchronize();
                    }

                    if (stripos($this->actions['flip'], 'v') !== false) {
                        if ($this->silent === false) {
                            $this->media->status('Flipping video');
                        }
                        $filters->vflip()->synchronize();
                    }
                }

                // CROP
                if (array_key_exists("crop", $this->actions) === true) {
                    $cropData = $this->actions["crop"];
                    $videoStream = $video->getStreams()->videos()->first();

                    $width = intval(arrayDefaultValue("width", $cropData, $videoStream->get('width')));
                    $height = intval(arrayDefaultValue("height", $cropData, $videoStream->get('height')));
                    $x = intval(arrayDefaultValue("x", $cropData, 0));
                    $y = intval(arrayDefaultValue("y", $cropData, 0));

                    $cropDimension = new Dimension($width, $height);

                    if ($this->silent === false) {
                        $this->media->status('Cropping video');
                    }
                    $filters->crop($cropDimension, $x, $y)->synchronize();
                }//end if

                $tempFilePath = tempnam(sys_get_temp_dir(), 'video-');
                $video->save(null, $tempFilePath);
                $this->media->changeStagingFile($tempFilePath);
            }//end if

            // Move file
            if (array_key_exists("move", $this->actions) === true) {
                if (array_key_exists("storage", $this->actions["move"]) === true) {
                    $newStorage = $this->actions["move"]["storage"];
                    if ($this->media->storage !== $newStorage) {
                        if (Storage::has($newStorage) === true) {
                            $this->media->storage = $newStorage;
                        } else {
                            $this->media->error("Cannot move file to '{$newStorage}' as it does not exist");
                        }
                    }
                }
            }

            // Finish media object
            $this->media->saveStagingFile(true, $this->silent);
            $this->media->ok();
            $this->media->save();
        } catch (\Exception $e) {
            $this->media->deleteStagingFile();

            if (strpos($this->media->status, 'Error') !== 0) {
                $this->media->error('Failed to process');
            }

            Log::error($e->getMessage());
            $this->fail($e);
        }//end try
    }
}
