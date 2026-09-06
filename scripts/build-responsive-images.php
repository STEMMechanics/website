<?php

// Deterministic static image variants; originals and their composition are preserved.
$root = dirname(__DIR__).'/public';
foreach (['home-schools' => [480, 768, 1024], 'home-green-screen' => [480], 'home-hero' => [1024]] as $name => $widths) {
    foreach ($widths as $width) {
        $image = new Imagick($root.'/'.$name.'.webp');
        if ($width > $image->getImageWidth()) {
            continue;
        }
        $image->thumbnailImage($width, 0);
        $image->stripImage();
        $image->setImageFormat('webp');
        $image->setImageCompressionQuality(78);
        $image->writeImage($root.'/'.$name.'-'.$width.'.webp');
        $image->clear();
    }
}
