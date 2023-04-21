<?php

namespace App\Console\Commands;

use App\Jobs\StoreUploadedFileJob;
use Illuminate\Console\Command;
use App\Models\Media;
use Symfony\Component\Console\Input\InputOption;

class MediaRebuild extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'media:rebuild';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Rebuild the media table';


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
    public function handle()
    {
        $replace = $this->option('replace');

        $media = Media::where(['variants' => ''])->orWhere(['variants' => '[]'])->orWhere(['variants' => '{}'])->get();
        foreach ($media as $medium) {
            StoreUploadedFileJob::dispatch($medium, '', $replace)->onQueue('media');
        }
    }
}
