<?php

namespace App\Jobs;

use App\Models\Media;
use App\Models\MediaJob;
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
use FFMpeg\FFProbe;
use FFMpeg\Format\VideoInterface;
use Intervention\Image\Facades\Image;

class MediaWorkerJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * MediaJob item
     *
     * @var MediaJob
     */
    protected $mediaJob;

    /**
     * Actions should be silent (not update the status field)
     *
     * @var boolean
     */
    protected $silent;

    /**
     * Create a new job instance.
     *
     * @param MediaJob   $mediaJob   The mediaJob model.
     * @param array   $actions The media actions to make.
     * @param boolean $silent  Update the media status with progress.
     * @return void
     */
    public function __construct(MediaJob $mediaJob, bool $silent = false)
    {
        $mediaJob = $mediaJob;
        $this->silent = $silent;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $media = null;
        $data = json_decode($this->mediaJob->data, true);

        // new Media();
        // $this->mediaJob->media_id = $media->id;


        try {
            // FILE
            if (array_key_exists('file', $data) === true) {
                if(file_exists($data['file']) === false) {
                    $errorStr = 'temporary upload file no longer exists';
                    $this->mediaJob->setStatusFailed($errorStr);
                    Log::info($errorStr);
                    throw new \Exception($errorStr);
                }

                // convert HEIC files to JPG
                $fileExtension = File::extension($data['file']);
                if ($fileExtension === 'heic') {
                    if ($this->silent === false) {
                        $this->mediaJob->setStatusProcessing(0, 'converting image');
                    }

                    // Get the path without the file name
                    $uploadedFileDirectory = dirname($data['file']);

                    // Convert the HEIC file to JPG
                    $jpgFileName = pathinfo($data['file'], PATHINFO_FILENAME) . '.jpg';
                    $jpgFilePath = $uploadedFileDirectory . '/' . $jpgFileName;
                    if (file_exists($jpgFilePath) === true) {
                        $errorStr = 'file already exists on server';
                        $this->mediaJob->setStatusFailed($errorStr);
                        Log::info($errorStr);
                        throw new \Exception($errorStr);
                    }

                    Image::make($data['file'])->save($jpgFilePath);

                    // Update the uploaded file path and file name
                    unlink($data['file']);
                    $filePath = $jpgFilePath;
                    $data['file'] = $jpgFileName;
                }//end if

                // get storage
                $storage = $data['storage'];
                if ($storage === '') {
                    if (strpos($data['mime_type'], 'image/') === 0) {
                        $storage = 'local';
                    } else {
                        $storage = 'cdn';
                    }
                }

                // Check if file already exists
                if (Storage::disk($storage)->exists($data['name']) === true) {
                    if (array_key_exists('replace', $data) === false || isTrue($data['replace']) === false) {
                        $this->mediaJob->setStatusFailed('file already exists on server');
                        $errorStr = "cannot upload file " . $storage . " " . // phpcs:ignore
                        "/ " . $data['name'] . " as it already exists";
                        Log::info($errorStr);
                        throw new \Exception($errorStr);
                    }
                }

                $media = new Media([
                    'user_id' => $this->mediaJob->user_id,
                    'title' => $data['title'],
                    'name' => $data['name'],
                    'mime_type' => $data['mime_type'],
                    'size' => $data['size'],
                    'storage' => $storage
                ]);

                $media->setStagingFile($filePath);
            } else {
                $media = $this->mediaJob->media()->first();
                if($media === null || $media->exists() === false) {
                    $errorStr = 'The media item no longer exists';
                    $this->mediaJob->setStatusFailed($errorStr);
                    Log::info($errorStr);
                    throw new \Exception($errorStr);
                }
            }

            // TODO:
            // mime_type may not be in data if we are just doing a transform...
            // if fails, need to delete the staging file
            // do not delete the file straight away incase we fail the transform
            // delete the media object if we fail and it is a new media object
            // UPDATE IN CONTROLLER NEEDS TO BE FIXED
            // STATUS field can be removed in Media object
            // Front end needs to support non status field and media jobs

            if(array_key_exists('transform', $data) === true) {
                $media->createStagingFile();
                $media->deleteFile();

                // Modifications
                if (strpos($media->mime_type, 'image/') === 0) {
                    $modified = false;
                    $image = Image::make($media->getStagingFilePath());

                    // ROTATE
                    if (array_key_exists("rotate", $data['transform']) === true) {
                        $rotate = intval($data['transform']['rotate']);
                        if ($rotate !== 0) {
                            if ($this->silent === false) {
                                $this->mediaJob->setStatusProcessing(0, 'rotating image');
                            }
                            $image = $image->rotate($rotate);
                            $modified = true;
                        }
                    }

                    // FLIP-H/V
                    if (array_key_exists('flip', $data['transform']) === true) {
                        if (stripos($data['transform']['flip'], 'h') !== false) {
                            if ($this->silent === false) {
                                $this->mediaJob->setStatusProcessing(0, 'flipping image');
                            }
                            $image = $image->flip('h');
                            $modified = true;
                        }

                        if (stripos($data['transform']['flip'], 'v') !== false) {
                            if ($this->silent === false) {
                                $this->mediaJob->setStatusProcessing(0, 'flipping image');
                            }
                            $image = $image->flip('v');
                            $modified = true;
                        }
                    }

                    // CROP
                    if (array_key_exists("crop", $data['transform']) === true) {
                        $cropData = $data['transform']['crop'];
                        $width = intval(arrayDefaultValue("width", $cropData, $image->getWidth()));
                        $height = intval(arrayDefaultValue("height", $cropData, $image->getHeight()));
                        $x = intval(arrayDefaultValue("x", $cropData, 0));
                        $y = intval(arrayDefaultValue("y", $cropData, 0));

                        if ($this->silent === false) {
                            $this->mediaJob->setStatusProcessing(0, 'cropping image');
                        }
                        $image = $image->crop($width, $height, $x, $y);
                        $modified = true;
                    }//end if

                    if ($modified === true) {
                        $image->save();
                    }
                } elseif (strpos($data['mime_type'], 'video/') === 0) {
                    $stagingFilePath = $media->getStagingFilePath();
                    $ffmpeg = FFMpeg\FFMpeg::create();
                    $video = $ffmpeg->open($stagingFilePath);
                    $format = $this->detectVideoFormat($stagingFilePath);
                    $modified = false;

                    if ($format === null) {
                        $this->mediaJob->setStatusFailed('Unsupported video format');
                        return;
                    }

                    /** @var FFMpeg\Media\Video::filters */
                    $filters = $video->filters();

                    // ROTATE
                    if (array_key_exists("rotate", $data['transform']) === true) {
                        $rotate = intval($data['transform']['rotate']);
                        $rotate = (($rotate % 360 + 360) % 360); // remove excess rotations
                        $rotate = intval(round($rotate / 90) * 90); // round to nearest 90%

                        if ($rotate > 0) {
                            $this->mediaJob->setStatusProcessing(0, 'rotating video');

                            if ($rotate === 90) {
                                $filters->rotate(FFMpeg\Filters\Video\RotateFilter::ROTATE_270);
                                $modified = true;
                            } elseif ($rotate === 180) {
                                $filters->rotate(FFMpeg\Filters\Video\RotateFilter::ROTATE_180);
                                $modified = true;
                            } elseif ($rotate === 270) {
                                $filters->rotate(FFMpeg\Filters\Video\RotateFilter::ROTATE_90);
                                $modified = true;
                            }
                        }
                    }

                    // FLIP-H/V
                    if (array_key_exists('flip', $data['transform']) === true) {
                        if (stripos($data['transform']['flip'], 'h') !== false) {
                            if ($this->silent === false) {
                                $media->status('Flipping video');
                            }
                            $filters->hflip()->synchronize();
                            $modified = true;
                        }

                        if (stripos($data['transform']['flip'], 'v') !== false) {
                            if ($this->silent === false) {
                                $media->status('Flipping video');
                            }
                            $filters->vflip()->synchronize();
                            $modified = true;
                        }
                    }

                    // CROP
                    if (array_key_exists("crop", $data['transform']) === true) {
                        $cropData = $data['transform']['crop'];
                        $videoStream = $video->getStreams()->videos()->first();

                        $width = intval(arrayDefaultValue("width", $cropData, $videoStream->get('width')));
                        $height = intval(arrayDefaultValue("height", $cropData, $videoStream->get('height')));
                        $x = intval(arrayDefaultValue("x", $cropData, 0));
                        $y = intval(arrayDefaultValue("y", $cropData, 0));

                        $cropDimension = new Dimension($width, $height);

                        if ($this->silent === false) {
                            $media->status('Cropping video');
                        }
                        $filters->crop($cropDimension, $x, $y)->synchronize();
                        $modified = true;
                    }//end if

                    $tempFilePath = generateTempFilePath(pathinfo($stagingFilePath, PATHINFO_EXTENSION));
                    if (method_exists($format, 'on') === true) {
                        $media = $media;
                        $format->on('progress', function ($video, $format, $percentage) use ($media) {
                            $media->status("{$percentage}% transcoded");
                        });
                    }

                    if($modified === true) {
                        $video->save($format, $tempFilePath);
                        $media->changeStagingFile($tempFilePath);
                    }
                }//end if

                // Move file
                if (array_key_exists("move", $data['transform']) === true) {
                    if (array_key_exists("storage", $data['transform']['move']) === true) {
                        $newStorage = $data['transform']['move"]["storage'];
                        if ($media->storage !== $newStorage) {
                            if (Storage::has($newStorage) === true) {
                                $media->storage = $newStorage;
                            } else {
                                $media->error("Cannot move file to '{$newStorage}' as it does not exist");
                            }
                        }
                    }
                }
            }

            // Finish media object
            $media->saveStagingFile(true);
            $media->save();
            $this->mediaJob->setStatusComplete();
        } catch (\Exception $e) {
            $media->deleteStagingFile();

            // if (strpos($media->status, 'Error') !== 0) {
            //     $media->error('Failed to process the file');
            // }

            Log::error($e->getMessage() . "\n" . $e->getFile() . " - " . $e->getLine() . "\n" . $e->getTraceAsString());
            $this->fail($e);
        }//end try
    }

    /**
     * Detects the format of a video using FFProbe
     *
     * @param string $videoPath The video file path.
     * @return VideoInterface | null
     */
    public function detectVideoFormat(string $videoPath): VideoInterface | null
    {
        $ffprobe = FFProbe::create();

        $videoStream = $ffprobe
            ->streams($videoPath)  // Provide the path to the video file
            ->videos()             // Filter video streams
            ->first();

        $codecName = $videoStream->get('codec_name');

        $codecToFormatClass = [
            'h264' => 'FFMpeg\Format\Video\X264',
            'wmv2' => 'FFMpeg\Format\Video\WMV',
            'vp9'  => 'FFMpeg\Format\Video\WebM',
            'theora' => 'FFMpeg\Format\Video\Ogg',
            'mpeg4' => 'FFMpeg\Format\Video\Mpeg4',
            // Add more mappings as needed
        ];

        if (isset($codecToFormatClass[$codecName]) === false) {
            Log::info("Unsupported codec: $codecName");
            return null;
        }

        $formatClassName = $codecToFormatClass[$codecName];

        if (class_exists($formatClassName) === false) {
            Log::info("Format class does not exist: $formatClassName");
            return null;
        }

        return new $formatClassName();
    }
}
