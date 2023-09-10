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

/** @property on $format */
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
     * Create a new job instance.
     *
     * @param MediaJob $mediaJob The mediaJob model.
     * @return void
     */
    public function __construct(MediaJob $mediaJob)
    {
        $this->mediaJob = $mediaJob;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        $media = $this->mediaJob->media()->first();
        $newMedia = false;
        $data = json_decode($this->mediaJob->data, true);

        try {
            // FILE
            if (array_key_exists('file', $data) === true) {
                if (file_exists($data['file']) === false) {
                    $this->throwMediaJobFailure('temporary upload file no longer exists');
                }

                // convert HEIC files to JPG
                $fileExtension = File::extension($data['file']);
                if ($fileExtension === 'heic') {
                    $this->mediaJob->setStatusProcessing(0, 0, 'converting image');

                    // Get the path without the file name
                    $uploadedFileDirectory = dirname($data['file']);

                    // Convert the HEIC file to JPG
                    $jpgFileName = pathinfo($data['file'], PATHINFO_FILENAME) . '.jpg';
                    $jpgFilePath = $uploadedFileDirectory . '/' . $jpgFileName;
                    if (file_exists($jpgFilePath) === true) {
                        $this->throwMediaJobFailure('file already exists on server');
                    }

                    Image::make($data['file'])->save($jpgFilePath);

                    // Update the uploaded file path and file name
                    unlink($data['file']);
                    $data['file'] = $jpgFileName;
                }//end if

                // get storage
                $storage = '';
                if ($media === null) {
                    if (array_key_exists('storage', $data) === true) {
                        $storage = $data['storage'];
                    }
                } else {
                    $storage = $media->storage;
                }

                if ($storage === '') {
                    if (strpos($data['mime_type'], 'image/') === 0) {
                        $storage = 'local';
                    } else {
                        $storage = 'cdn';
                    }
                }

                // Check if file already exists
                $exists = Storage::disk($storage)->exists($data['name']);
                if ($exists === true) {
                    if (array_key_exists('noreplace', $data) === true && isTrue($data['noreplace']) === true) {
                        $this->throwMediaJobFailure('file already exists on server');
                    }
                }

                if($exists === true) {
                    $pathInfo = pathinfo($data['name']);
                    $basename = $pathInfo['filename'];
                    $extension = $pathInfo['extension'];
                    $index = 0;

                    do {
                        $index++;
                        $data['name'] = $basename . '-' . $index . '.' . $extension;
                    } while (Storage::disk($storage)->exists($data['name']) === true);
                }

                if ($media === null) {
                    $newMedia = true;
                    $media = new Media([
                        'user_id' => $this->mediaJob->user_id,
                        'title' => $data['title'],
                        'name' => $data['name'],
                        'mime_type' => $data['mime_type'],
                        'size' => $data['size'],
                        'storage' => $storage
                    ]);
                }//end if

                $media->setStagingFile($data['file']);
            } else {
                if ($media === null) {
                    $this->throwMediaJobFailure('The media item no longer exists');
                }
            }//end if

            if (array_key_exists('transform', $data) === true) {
                $media->createStagingFile();

                // Modifications
                if (strpos($media->mime_type, 'image/') === 0) {
                    $modified = false;
                    $image = Image::make($media->getStagingFilePath());

                    // ROTATE
                    if (array_key_exists("rotate", $data['transform']) === true) {
                        $rotate = intval($data['transform']['rotate']);
                        if ($rotate !== 0) {
                            $this->mediaJob->setStatusProcessing(0, 0, 'rotating image');
                            $image = $image->rotate($rotate);
                            $modified = true;
                        }
                    }

                    // FLIP-H/V
                    if (array_key_exists('flip', $data['transform']) === true) {
                        if (stripos($data['transform']['flip'], 'h') !== false) {
                            $this->mediaJob->setStatusProcessing(0, 0, 'flipping image');
                            $image = $image->flip('h');
                            $modified = true;
                        }

                        if (stripos($data['transform']['flip'], 'v') !== false) {
                            $this->mediaJob->setStatusProcessing(0, 0, 'flipping image');
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

                        $this->mediaJob->setStatusProcessing(0, 0, 'cropping image');
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
                            $this->mediaJob->setStatusProcessing(0, 0, 'rotating video');

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
                            $this->mediaJob->setStatusProcessing(0, 0, 'flipping video');
                            $filters->hflip()->synchronize();
                            $modified = true;
                        }

                        if (stripos($data['transform']['flip'], 'v') !== false) {
                            $this->mediaJob->setStatusProcessing(0, 0, 'flipping video');
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

                        $this->mediaJob->setStatusProcessing(0, 0, 'cropping video');
                        $filters->crop($cropDimension, $x, $y)->synchronize();
                        $modified = true;
                    }//end if

                    $tempFilePath = generateTempFilePath(pathinfo($stagingFilePath, PATHINFO_EXTENSION));
                    if (method_exists($format, 'on') === true) {
                        $mediaJob = $this->mediaJob;
                        $format->on('progress', function ($video, $format, $percentage) use ($mediaJob) {
                            $mediaJob->setStatusProcessing($percentage, 100, 'transcoded');
                        });
                    }

                    if ($modified === true) {
                        $video->save($format, $tempFilePath);
                        $media->changeStagingFile($tempFilePath);
                    }
                }//end if

                // Move file
                if (array_key_exists('move', $data['transform']) === true) {
                    if (array_key_exists('storage', $data['transform']['move']) === true) {
                        $newStorage = $data['transform']['move']['storage'];
                        if ($media->storage !== $newStorage) {
                            if (Storage::has($newStorage) === true) {
                                $media->createStagingFile();
                                $media->storage = $newStorage;
                            } else {
                                $this->throwMediaJobFailure("Cannot move file to '{$newStorage}' as it does not exist");
                            }
                        }
                    }
                }
            }//end if

            // Update attributes
            if (array_key_exists('title', $data) === true) {
                $media->title = $data['title'];
            }

            // Finish media object
            if ($media->hasStagingFile() === true) {
                $this->mediaJob->setStatusProcessing(0, 0, 'uploading to cdn');
                $media->deleteFile();
                $media->saveStagingFile(true);
            }

            $media->save();
            $this->mediaJob->media_id = $media->id;
            $this->mediaJob->setStatusComplete();
        } catch (\Exception $e) {
            if ($this->mediaJob->status !== 'failed') {
                $this->mediaJob->setStatusFailed('Unexpected server error occurred');
            }

            if ($media !== null) {
                $media->deleteStagingFile();
                if ($newMedia === true) {
                    $media->delete();
                }
            }

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

    /**
     * Set failure status of MediaJob and throw exception.
     *
     * @param string $error The failure message.
     * @return void
     */
    private function throwMediaJobFailure(string $error): void
    {
        $this->mediaJob->setStatusFailed($error);
        throw new \Exception($error);
    }
}
