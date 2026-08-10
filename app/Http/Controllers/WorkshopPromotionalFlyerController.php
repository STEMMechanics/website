<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
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
            'description' => $this->flyerDescription($workshop),
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
        $media = $workshop->hero;
        if ($media === null) {
            return null;
        }

        try {
            $variantKey = $media->hash.'-md';
            $variantStorage = $media->variantStorage();
            $contents = $variantStorage->exists($variantKey)
                ? $variantStorage->get($variantKey)
                : $media->sourceStorage()->get($media->hash);

            if (! is_string($contents) || $contents === '') {
                return null;
            }

            $image = (new ImageManager(new Driver))->read($contents)->cover(1200, 425);

            return $image->toJpeg(84)->toDataUri();
        } catch (Throwable) {
            return null;
        }
    }

    private function flyerDescription(Workshop $workshop, int $limit = 165): string
    {
        $description = trim((string) ($workshop->summary ?: $workshop->content));
        if ($description === '') {
            return '';
        }

        $description = preg_replace(
            '/<br\s*\/?>|<\/(?:p|div|li|h[1-6]|tr|td)>/i',
            ' ',
            $description,
        ) ?? $description;
        $description = html_entity_decode(strip_tags($description), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $description = preg_replace(
            '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}\x{200D}\x{20E3}]/u',
            '',
            $description,
        ) ?? $description;

        return Str::limit((string) Str::of($description)->squish(), $limit);
    }
}
