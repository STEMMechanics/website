<?php

namespace App\Jobs\Media;

use App\Models\Media;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RegenerateMissingVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Media::query()
            ->chunkById(100, function ($mediaBatch): void {
                foreach ($mediaBatch as $media) {
                    $variantTypes = $media->getVariantTypes();
                    if ($variantTypes === []) {
                        continue;
                    }

                    $hasMissingVariant = false;
                    foreach (array_keys($variantTypes) as $variantName) {
                        if (!$media->hasVariant($variantName)) {
                            $hasMissingVariant = true;
                            break;
                        }
                    }

                    if (!$hasMissingVariant) {
                        continue;
                    }

                    $media->generateVariants(false);
                }
            }, 'name');
    }
}
