<?php

namespace Tests\Unit;

use App\Models\Media;
use App\Services\PdfAttachmentAppender;
use Illuminate\Support\Collection;
use Mockery;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

class PdfAttachmentAppenderTest extends TestCase
{
    public function test_it_appends_images_and_all_pdf_pages_but_skips_video(): void
    {
        $basePdf = $this->pdfWithPages(1);
        $attachedPdf = $this->temporaryPath('pdf');
        file_put_contents($attachedPdf, $this->pdfWithPages(2));
        $image = $this->temporaryPath('png');
        $imageResource = imagecreatetruecolor(20, 30);
        imagepng($imageResource, $image);
        imagedestroy($imageResource);
        $video = $this->temporaryPath('mp4');
        file_put_contents($video, 'not a printable attachment');

        $attachments = new Collection([
            $this->media('image/png', $image),
            $this->media('application/pdf', $attachedPdf),
            $this->media('video/mp4', $video),
        ]);

        $result = app(PdfAttachmentAppender::class)->append($basePdf, $attachments);
        $output = $this->temporaryPath('pdf');
        file_put_contents($output, $result);

        try {
            $this->assertSame(4, (new Fpdi())->setSourceFile($output));
        } finally {
            @unlink($output);
        }
    }

    private function media(string $mimeType, string $path): Media
    {
        $media = Mockery::mock(Media::class)->makePartial();
        $media->mime_type = $mimeType;
        $media->shouldReceive('getAsTempFile')->once()->andReturn($path);

        return $media;
    }

    private function pdfWithPages(int $pages): string
    {
        $pdf = new \FPDF();
        $pdf->SetFont('Helvetica', '', 10);
        for ($page = 0; $page < $pages; $page++) {
            $pdf->AddPage();
            $pdf->Cell(40, 10, 'Test page');
        }

        return $pdf->Output('S');
    }

    private function temporaryPath(string $extension): string
    {
        return sys_get_temp_dir().'/pdf_attachment_'.bin2hex(random_bytes(8)).'.'.$extension;
    }
}
