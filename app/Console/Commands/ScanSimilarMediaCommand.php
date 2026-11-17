<?php

namespace App\Console\Commands;

use App\Services\ImagePerceptualHash;
use App\Services\MediaDuplicateService;
use Illuminate\Console\Command;

class ScanSimilarMediaCommand extends Command
{
    protected $signature = 'media:scan-similar';

    protected $description = 'Queue unscanned image hashes and refresh similar-image matches';

    public function handle(MediaDuplicateService $duplicates, ImagePerceptualHash $hasher): int
    {
        $queued = $duplicates->queueUnscannedImageHashes();
        $matches = $duplicates->refreshSimilarPairs($hasher);
        $this->info('Queued '.$queued.' unscanned image'.($queued === 1 ? '' : 's').'; refreshed '.$matches.' possible similar-image pair'.($matches === 1 ? '' : 's').'.');

        return self::SUCCESS;
    }
}
