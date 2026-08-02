<?php

namespace App\Services;

use App\Models\Media;
use Illuminate\Support\Collection;
use Imagick;
use setasign\Fpdi\Fpdi;
use Throwable;

class PdfAttachmentAppender
{
    /**
     * @param  Collection<int, Media>  $attachments
     */
    public function append(string $document, Collection $attachments): string
    {
        $pdf = new NumberedFpdi();
        $pdf->AliasNbPages();
        $temporaryFiles = [];

        try {
            $source = $this->temporaryFile($document, 'pdf');
            $temporaryFiles[] = $source;
            $temporaryFiles = [...$temporaryFiles, ...$this->appendPdf($pdf, $source)];

            foreach ($attachments as $attachment) {
                $path = $attachment->getAsTempFile();
                if ($path === null) {
                    continue;
                }

                $temporaryFiles[] = $path;
                $mimeType = strtolower((string) $attachment->mime_type);

                try {
                    if ($mimeType === 'application/pdf') {
                        $temporaryFiles = [...$temporaryFiles, ...$this->appendPdf($pdf, $path)];
                    } elseif (str_starts_with($mimeType, 'image/')) {
                        $image = $this->normaliseImage($path);
                        $temporaryFiles[] = $image;
                        $this->appendImage($pdf, $image);
                    }
                } catch (Throwable) {
                    // A broken or unsupported attachment should not prevent the run sheet from printing.
                }
            }

            return $pdf->Output('S');
        } finally {
            foreach (array_unique($temporaryFiles) as $path) {
                if (is_string($path) && is_file($path)) {
                    @unlink($path);
                }
            }
        }
    }

    /** @return list<string> */
    private function appendPdf(Fpdi $target, string $path): array
    {
        try {
            $pageCount = $target->setSourceFile($path);
            for ($page = 1; $page <= $pageCount; $page++) {
                $template = $target->importPage($page);
                $size = $target->getTemplateSize($template);
                $target->AddPage($size['orientation'], [$size['width'], $size['height']]);
                $target->useTemplate($template);
            }

            return [];
        } catch (Throwable) {
            return $this->appendRasterizedPdf($target, $path);
        }
    }

    /** @return list<string> */
    private function appendRasterizedPdf(Fpdi $target, string $path): array
    {
        $document = new Imagick();
        $document->setResolution(150, 150);
        $document->readImage($path);
        $temporaryFiles = [];

        foreach ($document as $page) {
            $page->setImageBackgroundColor('white');
            $page->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
            $page->setImageFormat('png');
            $page->stripImage();
            $imagePath = $this->temporaryFile('', 'png');
            $page->writeImage($imagePath);
            $temporaryFiles[] = $imagePath;
            $this->appendImage($target, $imagePath);
        }

        $document->clear();

        return $temporaryFiles;
    }

    private function appendImage(Fpdi $target, string $path): void
    {
        [$width, $height] = getimagesize($path) ?: [1, 1];
        $landscape = $width > $height;
        $pageWidth = $landscape ? 297.0 : 210.0;
        $pageHeight = $landscape ? 210.0 : 297.0;
        $margin = 10.0;
        $scale = min(($pageWidth - ($margin * 2)) / $width, ($pageHeight - ($margin * 2)) / $height);
        $renderWidth = $width * $scale;
        $renderHeight = $height * $scale;

        $target->AddPage($landscape ? 'L' : 'P', 'A4');
        $target->Image(
            $path,
            ($pageWidth - $renderWidth) / 2,
            ($pageHeight - $renderHeight) / 2,
            $renderWidth,
            $renderHeight,
            'PNG',
        );
    }

    private function normaliseImage(string $path): string
    {
        $image = new Imagick($path);
        $image->setIteratorIndex(0);
        $image->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $image->setImageFormat('png');
        $image->setImageAlphaChannel(Imagick::ALPHACHANNEL_REMOVE);
        $image->stripImage();

        $output = $this->temporaryFile('', 'png');
        $image->writeImage($output);
        $image->clear();

        return $output;
    }

    private function temporaryFile(string $contents, string $extension): string
    {
        $base = tempnam(sys_get_temp_dir(), 'workshop_pdf_');
        if ($base === false) {
            throw new \RuntimeException('Could not create a temporary PDF file.');
        }

        $path = $base.'.'.$extension;
        @unlink($base);
        file_put_contents($path, $contents);

        return $path;
    }
}

class NumberedFpdi extends Fpdi
{
    public function Footer(): void
    {
        $this->SetY(-7);
        $this->SetFont('Helvetica', '', 8);
        $this->SetTextColor(64, 64, 64);
        $this->Cell(0, 4, 'Page '.$this->PageNo().' of {nb}', 0, 0, 'R');
    }
}
