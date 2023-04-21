<?php

namespace App\Console\Commands;

use App\Jobs\StoreUploadedFileJob;
use Illuminate\Console\Command;
use App\Models\Media;
use File;

class MigrateUploads extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'uploads:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate the uploads folder to the CDN';


    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $replace = $this->option('replace');

        $files = File::allFiles(public_path('uploads'));

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_BASENAME);

            $medium = Media::where('name', $filename)->first();

            if ($medium !== null) {
                $medium->update(['status' => 'Processing media']);
                StoreUploadedFileJob::dispatch($medium, $file, $replace)->onQueue('media');
            } else {
                unlink($file);
            }
        }
    }
}
