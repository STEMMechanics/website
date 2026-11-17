<?php

namespace App\Console\Commands;

use App\Services\MediaDuplicateService;
use Illuminate\Console\Command;

class ScanSimilarMediaCommand extends Command
{
    protected $signature = 'media:scan-similar';

    protected $description = 'Queue perceptual-hash generation for unscanned images';

    public function handle(MediaDuplicateService $duplicates): int
    {
        $queued = $duplicates->queueUnscannedImageHashes();
        $this->info($queued === 0
            ? 'All images have already been scanned.'
            : 'Queued '.$queued.' image'.($queued === 1 ? '' : 's').' for similarity scanning.');

        return self::SUCCESS;
    }
}
