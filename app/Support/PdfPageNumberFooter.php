<?php

namespace App\Support;

use Barryvdh\DomPDF\PDF;

class PdfPageNumberFooter
{
    public static function add(PDF $pdf): void
    {
        $pdf->render();
        $domPdf = $pdf->getDomPDF();
        $canvas = $domPdf->getCanvas();
        $fontMetrics = $domPdf->getFontMetrics();
        $font = $fontMetrics->getFont('Helvetica', 'normal');
        $size = 8;
        $text = 'Page {PAGE_NUM} of {PAGE_COUNT}';
        $textWidth = $fontMetrics->getTextWidth('Page 00 of 00', $font, $size);

        $canvas->page_text(
            $canvas->get_width() - 30 - $textWidth,
            $canvas->get_height() - 20,
            $text,
            $font,
            $size,
            [0.25, 0.25, 0.25]
        );
    }
}
