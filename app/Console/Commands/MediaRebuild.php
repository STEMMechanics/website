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
    protected function configure(): void
    {
        $this->addOption(
            'replace',
            null,
            InputOption::VALUE_NONE,
            'Replace existing files'
        );

        $this->addOption(
            'all',
            null,
            InputOption::VALUE_NONE,
            'Rebuild all variants'
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
        $all = $this->option('replace');

        $media = [];
        if ($all === true) {
            $media = Media::all();
        } else {
            $media = Media::where(['variants' => ''])->orWhere(['variants' => '[]'])->orWhere(['variants' => '{}'])->get();
        }

        foreach ($media as $medium) {
            StoreUploadedFileJob::dispatch($medium, '', $replace)->onQueue('media');
        }
    }
}
