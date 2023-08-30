<?php

namespace App\Models;

use App\Jobs\MediaWorkerJob;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MediaJob extends Model
{
    use HasFactory;
    use Uuids;

    public function setStatusFailed(string $statusText = ''): void {
        $this->setStatus('failed', $statusText, 0);
    }

    public function setStatusQueued(): void {
        $this->setStatus('queued', '', 0);
    }

    public function setStatusWaiting(): void {
        $this->setStatus('waiting', '', 0);
    }

    public function setStatusProcessing(int $progress = 0, string $statusText = ''): void {
        if($statusText === '') {
            $statusText = $this->status_text;
        }

        $this->setStatus('processing', $statusText, $progress);
    }

    public function setStatusComplete(): void {
        $this->setStatus('complete');
    }

    public function setStatusInvalid(): void {
        $this->setStatus('invalid');
    }

    public function setStatus(string $status, string $text = '', int $progress = 0): void {
        $this->status = $status;
        $this->status_text = $text;
        $this->progress = $progress;
        $this->save();
    }


    public function process(): void
    {
        $data = json_decode($this->data, true);
        if($data !== null) {
            if(array_key_exists('chunks', $data) === true) {
                if(array_key_exists('chunk_count', $data) === false || array_key_exists('name', $data) === false) {
                    $this->setStatusInvalid();
                    return;
                }

                $numChunks = count($data['chunks']);
                $maxChunks = intval($data['chunk_count']);
                if($numChunks >= $maxChunks) {
                    // merge file and dispatch
                    $percentage = 0;
                    $percentageStep = 100 / $maxChunks;
                    $this->setStatusProcessing($percentage, 'combining chunks');

                    $newFile = generateTempFilePath(pathinfo($data['name'], PATHINFO_EXTENSION));
                    $failed = false;
                    
                    for($index = 1; $index <= $maxChunks; $index++) {
                        if(array_key_exists($index, $data['chunks']) === false) {
                            $failed = true;
                        } else {
                            $tempFileName = $data['chunks'][$index];

                            if($failed === false) {
                                $chunkContents = file_get_contents($tempFileName);
                                if($chunkContents === false) {
                                    $failed = true;
                                } else {
                                    file_put_contents($newFile, $chunkContents, FILE_APPEND);
                                }
                            }

                            unlink($tempFileName);
                            $this->setStatusProcessing($percentage += $percentageStep);
                        }
                    }

                    unset($data['chunks']);
                    $this->data = json_encode($data);

                    if($failed === false) {
                        $this->setStatusInvalid();
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $newFile);
                        finfo_close($finfo);
        
                        $data['file']['path'] = $newFile;
                        $data['file']['size'] = filesize($newFile);
                        $data['file']['mime_type'] = $mime;
 
                        $this->setStatusQueued();
                        MediaWorkerJob::dispatch($this);
                    }
                }
            } else if(array_key_exists('file', $data) || array_key_exists('transform', $data)) {
                $this->setStatusQueued();
                MediaWorkerJob::dispatch($this);
            }
        }
    }

    // public const INVALID_FILE_ERROR         = 1;
    // public const FILE_SIZE_EXCEEDED_ERROR   = 2;
    // public const FILE_NAME_EXISTS_ERROR     = 3;
    // public const TEMP_FILE_ERROR            = 4;

    // /**
    //  * Set the Media Job to failed
    //  * 
    //  * @var string $msg The status message to save.
    //  * @return void
    //  */
    // public function failed(string $msg = ''): void
    // {
    //     $data = [];

    //     try {
    //         $data = json_decode($this->data, true);
    //     } catch(\Exception $e) {
    //         /* empty */
    //     }

    //     if(array_key_exists('chunks', $data) === true) {
    //         foreach($data['chunks'] as $num => $path) {
    //             if(file_exists($path) === true) {
    //                 unlink($path);
    //             }
    //         }

    //         unset($data['chunks']);
    //         $this->data = json_encode($data);
    //     }

    //     $this->status = 'failed';
    //     $this->status_text = $msg;
    //     $this->progress = 0;
    //     $this->save();
    // }


    /**
     * Return the job owner
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Return the media item
     *
     * @return BelongsTo
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
