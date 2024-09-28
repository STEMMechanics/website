<?php

namespace App\Providers;

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
        $options = new QROptions;
        $options->outputBase64        = false;
        $options->imageTransparent    = true;
        return (new QRCode($options))->render($qrText);
    }
}
