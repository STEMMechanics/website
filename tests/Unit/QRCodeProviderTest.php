<?php

namespace Tests\Unit;

use App\Providers\QRCodeProvider;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use DOMDocument;
use Imagick;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QRCodeProviderTest extends TestCase
{
    public static function payloads(): array
    {
        return [
            'ticket reference' => ['STEM-TICKET-ABC123'],
            'authenticator setup' => ['otpauth://totp/STEMMechanics%3Amember%40example.com?secret=JBSWY3DPEHPK3PXP&issuer=STEMMechanics&algorithm=SHA256&digits=6&period=30'],
            'bitcoin payment' => ['bitcoin:bc1qexample?amount=0.001&label=Workshop%20sponsorship'],
        ];
    }

    #[DataProvider('payloads')]
    public function test_svg_output_can_be_decoded_to_the_original_payload(string $payload): void
    {
        $provider = new QRCodeProvider;
        $svg = $provider->getQRCodeImage($payload, 240);
        $this->assertSame('image/svg+xml', $provider->getMimeType());
        $this->assertStringNotContainsString('data:image/', $svg);

        $document = new DOMDocument;
        $this->assertTrue($document->loadXML($svg, LIBXML_NONET));
        $root = $document->documentElement;
        $this->assertSame('svg', $root->localName);
        $this->assertSame('http://www.w3.org/2000/svg', $root->namespaceURI);

        // Render the actual SVG, as an email or authenticator screen would,
        // then scan it rather than just checking that markup was returned.
        $dimensions = explode(' ', $root->getAttribute('viewBox'));
        $root->setAttribute('width', (string) ((int) $dimensions[2] * 8));
        $root->setAttribute('height', (string) ((int) $dimensions[3] * 8));
        $image = new Imagick;
        try {
            $image->setBackgroundColor('white');
            $image->readImageBlob($document->saveXML());
            $image->setImageFormat('png');
            $result = (new QRCode(new QROptions(['readerUseImagickIfAvailable' => true])))
                ->readFromBlob($image->getImageBlob());
            $this->assertSame($payload, $result->data);
        } finally {
            $image->clear();
        }
    }
}
