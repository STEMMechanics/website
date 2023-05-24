<?php

namespace App\Console\Commands;

use App\Jobs\StoreUploadedFileJob;
use Illuminate\Console\Command;
use App\Models\Media;
use File;
use Symfony\Component\Console\Input\InputOption;

class MediaMigrate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:migrate';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate the uploads folder to the CDN';


    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this->addOption(
            'replace',
            null,
            InputOption::VALUE_NONE,
            'Replace existing files'
        );
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(): void
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
