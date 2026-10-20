<?php

namespace App\Providers;

use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use RobThree\Auth\Providers\Qr\IQRCodeProvider;

class QRCodeProvider implements IQRCodeProvider
{
    public function getMimeType(): string
    {
        return 'image/svg+xml';
    }

    public function getQRCodeImage(string $qrText, int $size): string
    {
        $options = new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => false,
            'imageTransparent' => true,
        ]);

        return (new QRCode($options))->render($qrText);
    }
}
