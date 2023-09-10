<?php

namespace App\Console\Commands;

use App\Models\MediaJob;
use Illuminate\Console\Command;

class RemoveStaleMediaJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:remove-stale-media-jobs';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove media_jobs that have not been modified for 48 hours';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $threshold = now()->subHours(48);

        $staleJobs = MediaJob::where('updated_at', '<=', $threshold)->get();

        foreach ($staleJobs as $job) {
            $job->delete();
        }

        $this->info(count($staleJobs) . ' stale media_jobs removed.');
    }
}
