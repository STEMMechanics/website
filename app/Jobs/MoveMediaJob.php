<?php

namespace App\Jobs;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class MoveMediaJob implements ShouldQueue
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
    public $media;

    /**
     * New storage ID
     *
     * @var string
     */
    protected $newStorage;


    /**
     * Create a new job instance.
     *
     * @param Media  $media      The media model.
     * @param string $newStorage The new storage ID.
     * @return void
     */
    public function __construct(Media $media, string $newStorage)
    {
        $this->media = $media;
        $this->newStorage = $newStorage;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Don't continue if the media is already on the new storage disk
        if ($this->media->storage === $this->newStorage) {
            return;
        }

        $this->media->status = 'Moving file';
        $this->media->save();

        $files = ["/{$this->media->name}"];
        if (empty($this->media->variants) === false) {
            foreach ($this->media->variants as $variant => $name) {
                $files[] = "/{$name}";
            }
        }

        $this->media->invalidateCFCache();

        // Move the files from the old storage disk to the new storage disk
        foreach ($files as $file) {
            Storage::disk($this->newStorage)->put($file, Storage::disk($this->media->storage)->get($file));
            Storage::disk($this->media->storage)->delete($file);
        }

        // Update the media model with the new storage and save it to the database
        $this->media->storage = $this->newStorage;
        $this->media->status = 'OK';
        $this->media->save();
    }
}
