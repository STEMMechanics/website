<?php

namespace App\Services;

use Imagick;
use Throwable;

class ImagePerceptualHash
{
    public function generate(string $path): ?string
    {
        try {
            $image = new Imagick();
            $image->readImage($path.'[0]');
            $image->autoOrient();
            $image->setImageBackgroundColor('white');
            $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $image->transformImageColorspace(Imagick::COLORSPACE_GRAY);
            $image->resizeImage(9, 8, Imagick::FILTER_LANCZOS, 1, false);

            $bits = '';
            for ($y = 0; $y < 8; $y++) {
                for ($x = 0; $x < 8; $x++) {
                    $left = (float) ($image->getImagePixelColor($x, $y)->getColor(1)['r'] ?? 0);
                    $right = (float) ($image->getImagePixelColor($x + 1, $y)->getColor(1)['r'] ?? 0);
                    $bits .= $left > $right ? '1' : '0';
                }
            }

            $image->clear();
            $hash = '';
            foreach (str_split($bits, 4) as $nibble) {
                $hash .= dechex(bindec($nibble));
            }

            return in_array($hash, [str_repeat('0', 16), str_repeat('f', 16)], true) ? null : $hash;
        } catch (Throwable) {
            return null;
        }
    }

    public function distance(string $first, string $second): int
    {
        if (! preg_match('/^[0-9a-f]{16}$/', $first) || ! preg_match('/^[0-9a-f]{16}$/', $second)) {
            return 64;
        }

        $distance = 0;
        for ($index = 0; $index < 16; $index++) {
            $distance += self::NIBBLE_BITS[hexdec($first[$index]) ^ hexdec($second[$index])];
        }

        return $distance;
    }

    private const NIBBLE_BITS = [0, 1, 1, 2, 1, 2, 2, 3, 1, 2, 2, 3, 2, 3, 3, 4];
}
