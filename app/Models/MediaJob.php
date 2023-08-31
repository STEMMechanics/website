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


    /**
     * The default attributes.
     *
     * @var string[]
     */
    protected $attributes = [
        'user_id' => null,
        'media_id' => null,
        'status' => '',
        'status_text' => '',
        'progress' => 0,
        'data' => '',
    ];


    /**
     * Set MediaJob status to failed.
     *
     * @param string $statusText The failed reason.
     * @return void
     */
    public function setStatusFailed(string $statusText = ''): void
    {
        $this->setStatus('failed', $statusText, 0);
    }

    /**
     * Set MediaJob status to queued.
     *
     * @return void
     */
    public function setStatusQueued(): void
    {
        $this->setStatus('queued', '', 0);
    }

    /**
     * Set MediaJob status to waiting.
     *
     * @return void
     */
    public function setStatusWaiting(): void
    {
        $this->setStatus('waiting', '', 0);
    }

    /**
     * Set MediaJob status to processing.
     *
     * @param integer $progress   The processing percentage.
     * @param string  $statusText The processing status text.
     * @return void
     */
    public function setStatusProcessing(int $progress = 0, string $statusText = ''): void
    {
        if ($statusText === '') {
            $statusText = $this->status_text;
        }

        $this->setStatus('processing', $statusText, $progress);
    }

    /**
     * Set MediaJob status to complete.
     *
     * @return void
     */
    public function setStatusComplete(): void
    {
        $this->setStatus('complete');
    }

    /**
     * Set MediaJon status to invalid.
     *
     * @param string $text The status text.
     * @return void
     */
    public function setStatusInvalid(string $text = ''): void
    {
        $this->setStatus('invalid', $text);
    }

    /**
     * Set MediaJob status details.
     *
     * @param string  $status   The status string.
     * @param string  $text     The status text.
     * @param integer $progress The status percentage.
     * @return void
     */
    protected function setStatus(string $status, string $text = '', int $progress = 0): void
    {
        $this->status = $status;
        $this->status_text = $text;
        $this->progress = $progress;
        $this->save();
    }

    /**
     * Process the MediaJob.
     *
     * @return void
     */
    public function process(): void
    {
        $data = json_decode($this->data, true);
        if ($data !== null) {
            if (array_key_exists('chunks', $data) === true) {
                if (array_key_exists('chunk_count', $data) === false) {
                    $this->setStatusInvalid('chunk_count is missing');
                    return;
                }

                if (array_key_exists('name', $data) === false) {
                    $this->setStatusInvalid('name is missing');
                    return;
                }

                $numChunks = count($data['chunks']);
                $maxChunks = intval($data['chunk_count']);
                if ($numChunks >= $maxChunks) {
                    // merge file and dispatch
                    $percentage = 0;
                    $percentageStep = (100 / $maxChunks);
                    $this->setStatusProcessing($percentage, 'combining chunks');

                    $newFile = generateTempFilePath(pathinfo($data['name'], PATHINFO_EXTENSION));
                    $failed = false;

                    for ($index = 1; $index <= $maxChunks; $index++) {
                        if (array_key_exists($index, $data['chunks']) === false) {
                            $failed = `{$index} chunk is missing`;
                        } else {
                            $tempFileName = $data['chunks'][$index];

                            if ($failed === false) {
                                $chunkContents = file_get_contents($tempFileName);
                                if ($chunkContents === false) {
                                    $failed = `{$index} chunk is empty`;
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

                    if ($failed !== false) {
                        $this->setStatusInvalid($failed);
                    } else {
                        $finfo = finfo_open(FILEINFO_MIME_TYPE);
                        $mime = finfo_file($finfo, $newFile);
                        finfo_close($finfo);

                        $data['file'] = $newFile;
                        $data['size'] = filesize($newFile);
                        $data['mime_type'] = $mime;

                        $this->data = json_encode($data);
                        $this->setStatusQueued();
                        MediaWorkerJob::dispatch($this)->onQueue('media');
                    }
                }//end if
            } else {
                $this->setStatusQueued();
                MediaWorkerJob::dispatch($this)->onQueue('media');
            }//end if
        }//end if
    }

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
