<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class WeeklyWorkplanPdfService
{
    public function render(array $workplan): string
    {
        return Pdf::loadView('pdf.weekly-workplan', ['workplan' => $workplan])
            ->setPaper('a4', 'portrait')
            ->output();
    }

    public function filename(array $workplan): string
    {
        return 'weekly-workplan-'.$workplan['weekStart']->format('Y-m-d').'.pdf';
    }
}
