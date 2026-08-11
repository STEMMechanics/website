<?php

namespace App\Http\Controllers;

use App\Models\Workshop;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Intervention\Image\Drivers\Imagick\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class WorkshopPromotionalFlyerController extends Controller
{
    public function create(): View
    {
        $workshops = $this->upcomingWorkshops()->get();

        return view('admin.workshop.promotional-flyer', [
            'workshops' => $workshops,
            'previewWorkshops' => $workshops->mapWithKeys(fn (Workshop $workshop): array => [
                (string) $workshop->id => [
                    'id' => (string) $workshop->id,
                    'title' => $workshop->title,
                    'location' => $workshop->getLocationName(),
                    'description' => $this->flyerDescription($workshop),
                    'image' => $workshop->hero?->url('md') ?: null,
                    'date' => $workshop->starts_at?->format('D j M, g:i a'),
                    'duration' => $workshop->workshopDurationLabel(),
                    'price' => $workshop->currentTicketPriceAmount() > 0.0001
                        ? '$'.number_format($workshop->currentTicketPriceAmount(), 2)
                        : 'Free',
                ],
            ]),
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
            'customizations' => ['nullable', 'array'],
            'customizations.*' => ['array'],
            'customizations.*.description' => ['nullable', 'string', 'max:400'],
            'customizations.*.image_zoom' => ['required', 'integer', 'min:100', 'max:200'],
            'customizations.*.image_x' => ['required', 'integer', 'min:0', 'max:100'],
            'customizations.*.image_y' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $selectedIds = collect($validated['workshop_ids'])->map('strval')->values();
        $workshops = Workshop::query()
            ->with(['hero', 'location'])
            ->whereIn('id', $selectedIds->all())
            ->get()
            ->sortBy(fn (Workshop $workshop) => $selectedIds->search((string) $workshop->id))
            ->values();

        $customizations = collect($validated['customizations'] ?? []);
        $flyerWorkshops = $workshops->map(function (Workshop $workshop) use ($customizations): array {
            $customization = $customizations->get((string) $workshop->id, []);

            return [
                'workshop' => $workshop,
                'image' => $this->flyerImageData(
                    $workshop,
                    (int) ($customization['image_zoom'] ?? 100),
                    (int) ($customization['image_x'] ?? 50),
                    (int) ($customization['image_y'] ?? 50),
                ),
                'description' => $this->flyerDescription(
                    $workshop,
                    array_key_exists('description', $customization)
                        ? (string) $customization['description']
                        : null,
                ),
            ];
        });

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

    private function flyerImageData(
        Workshop $workshop,
        int $zoom = 100,
        int $positionX = 50,
        int $positionY = 50,
    ): ?string {
        $media = $workshop->hero;
        if ($media === null) {
            return null;
        }

        $candidates = [
            [$media->variantStorage(), $media->hash.'-md'],
            [$media->sourceStorage(), $media->hash],
        ];
        $lastError = null;

        foreach ($candidates as [$storage, $key]) {
            try {
                if (! $storage->exists($key)) {
                    continue;
                }

                $contents = $storage->get($key);
                if (! is_string($contents) || $contents === '') {
                    continue;
                }

                $zoom = max(100, min(200, $zoom));
                $positionX = max(0, min(100, $positionX));
                $positionY = max(0, min(100, $positionY));
                $image = (new ImageManager(new Driver))->read($contents);
                $coverScale = max(1200 / $image->width(), 425 / $image->height());
                $scale = $coverScale * ($zoom / 100);
                $scaledWidth = (int) ceil($image->width() * $scale);
                $scaledHeight = (int) ceil($image->height() * $scale);
                $offsetX = (int) round(($scaledWidth - 1200) * ($positionX / 100));
                $offsetY = (int) round(($scaledHeight - 425) * ($positionY / 100));
                $image = $image
                    ->resize($scaledWidth, $scaledHeight)
                    ->crop(1200, 425, $offsetX, $offsetY);

                return $image->toJpeg(84)->toDataUri();
            } catch (Throwable $exception) {
                $lastError = $exception;
            }
        }

        if ($lastError !== null) {
            Log::warning('Workshop flyer image could not be decoded.', [
                'media' => $media->name,
                'message' => $lastError->getMessage(),
            ]);
        }

        return null;
    }

    private function flyerDescription(Workshop $workshop, ?string $override = null, int $limit = 220): string
    {
        $description = trim($override ?? (string) ($workshop->summary ?: $workshop->content));
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

        $description = (string) Str::of($description)->squish();
        if (Str::length($description) <= $limit) {
            return $description;
        }

        $minimumSentenceLength = (int) floor($limit * 0.5);
        if (preg_match(
            '/^(.{'.$minimumSentenceLength.','.$limit.'}[.!?])(?=\s|$)/us',
            $description,
            $sentenceMatch,
        ) === 1) {
            return trim($sentenceMatch[1]);
        }

        return Str::limit($description, $limit);
    }
}
