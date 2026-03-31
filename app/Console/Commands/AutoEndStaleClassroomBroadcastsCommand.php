<?php

namespace App\Console\Commands;

use App\Services\Classroom\ClassroomBroadcastLifecycleService;
use Illuminate\Console\Command;

class AutoEndStaleClassroomBroadcastsCommand extends Command
{
    protected $signature = 'classroom:broadcasts:auto-end-stale';

    protected $description = 'End classroom livestreams that have not published a camera track within the timeout window.';

    public function handle(ClassroomBroadcastLifecycleService $broadcastLifecycleService): int
    {
        $endedCount = $broadcastLifecycleService->expireStaleBroadcasts(10);

        $this->info('Ended '.$endedCount.' stale livestream'.($endedCount === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
