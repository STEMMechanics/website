<?php

namespace App\Jobs\Media;

use App\Models\Media;
use App\Services\ImagePerceptualHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class GeneratePerceptualHash implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public string $mediaName) {}

    public function middleware(): array
    {
        return [(new WithoutOverlapping('perceptual-hash-'.$this->mediaName))->dontRelease()->expireAfter(600)];
    }

    public function handle(ImagePerceptualHash $hasher): void
    {
        $media = Media::query()->find($this->mediaName);
        if (! $media instanceof Media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return;
        }

        $path = $media->path();
        if ($path === null) {
            return;
        }

        $media->perceptual_hash = $hasher->generate($path);
        $media->perceptual_hash_scanned_at = now();
        $media->save();
    }
}
