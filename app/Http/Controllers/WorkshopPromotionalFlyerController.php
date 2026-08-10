<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class WorkshopPromotionalFlyerController extends Controller
{
    public function create(): View
    {
        return view('admin.workshop.promotional-flyer', [
            'workshops' => $this->upcomingWorkshops()->get(),
            'defaultFooter' => 'Book now at stemmechanics.com.au/workshops',
        ]);
    }

    public function generate(Request $request): Response
    {
        if (! class_exists(DomPdf::class)) {
            abort(500, 'PDF renderer is not available. Please install barryvdh/laravel-dompdf.');
        }

        $validated = $request->validate([
            'workshop_ids' => ['required', 'array', 'min:1', 'max:3'],
            'workshop_ids.*' => [
                'required',
                'string',
                'distinct',
                Rule::exists('workshops', 'id')->where(function ($query): void {
                    $query->where('starts_at', '>=', now())
                        ->whereIn('status', ['scheduled', 'open']);
                }),
            ],
            'footer' => ['required', 'string', 'max:220'],
        ]);

        $selectedIds = collect($validated['workshop_ids'])->map('strval')->values();
        $workshops = Workshop::query()
            ->with(['hero', 'location'])
            ->whereIn('id', $selectedIds->all())
            ->get()
            ->sortBy(fn (Workshop $workshop) => $selectedIds->search((string) $workshop->id))
            ->values();

        $flyerWorkshops = $workshops->map(fn (Workshop $workshop): array => [
            'workshop' => $workshop,
            'image' => $this->flyerImageData($workshop),
        ]);

        return DomPdf::loadView('pdf.workshop-promotional-flyer', [
            'flyerWorkshops' => $flyerWorkshops,
            'footer' => trim((string) $validated['footer']),
        ])->setOption([
            'enable_font_subsetting' => true,
            'isRemoteEnabled' => false,
        ])->setPaper('a4', 'landscape')->stream('workshop-promotional-flyer.pdf');
    }

    private function upcomingWorkshops()
    {
        return Workshop::query()
            ->with(['hero', 'location'])
            ->where('starts_at', '>=', now())
            ->whereIn('status', ['scheduled', 'open'])
            ->orderBy('starts_at');
    }

    private function flyerImageData(Workshop $workshop): ?string
    {
        $path = $workshop->hero?->variantPath('md') ?: $workshop->hero?->path();
        if (! is_string($path) || ! is_file($path)) {
            return null;
        }

        try {
            $image = (new ImageManager(new Driver()))->read($path)->cover(1200, 425);

            return $image->toJpeg(84)->toDataUri();
        } catch (Throwable) {
            return null;
        }
    }
}
