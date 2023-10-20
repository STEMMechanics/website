<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupTempFiles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-temp-files';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete temporary files that are older that 1 day';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
    {
        $keepTime = (1 * 24 * 60 * 60); // 1 Day
        $currentTimeStamp = time();
        $deletedFileCount = 0;

        foreach (glob(storage_path('app/tmp/*')) as $filename) {
            $fileModifiedTimeStamp = filemtime($filename);
            if (($currentTimeStamp - $fileModifiedTimeStamp) > $keepTime) {
                unlink($filename);
                $deletedFileCount++;
            }
        }

        $this->comment('Deleted ' . $deletedFileCount . ' files');
    }
}
