<?php

namespace Tests\Unit;

use App\Services\ImagePerceptualHash;
use Imagick;
use ImagickDraw;
use Tests\TestCase;

class ImagePerceptualHashTest extends TestCase
{
    public function test_resized_and_recompressed_versions_have_similar_hashes(): void
    {
        $pngPath = tempnam(sys_get_temp_dir(), 'perceptual-original-').'.png';
        $jpegPath = tempnam(sys_get_temp_dir(), 'perceptual-copy-').'.jpg';

        $image = new Imagick();
        $image->newImage(320, 200, 'white');
        $draw = new ImagickDraw();
        $draw->setFillColor('black');
        $draw->circle(90, 80, 145, 80);
        $draw->setFillColor('gray');
        $draw->rectangle(180, 45, 290, 165);
        $image->drawImage($draw);
        $image->setImageFormat('png');
        $image->writeImage($pngPath);
        $image->resizeImage(640, 400, Imagick::FILTER_LANCZOS, 1);
        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality(65);
        $image->writeImage($jpegPath);
        $image->clear();

        $hasher = app(ImagePerceptualHash::class);
        $first = $hasher->generate($pngPath);
        $second = $hasher->generate($jpegPath);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertLessThanOrEqual(8, $hasher->distance($first, $second));

        @unlink($pngPath);
        @unlink($jpegPath);
    }
}
