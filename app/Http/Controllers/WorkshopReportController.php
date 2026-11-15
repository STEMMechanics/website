<?php

namespace App\Http\Controllers;

use App\Models\Media;
use App\Models\MediaDownload;
use App\Models\User;
use App\Models\Workshop;
use App\Services\WorkshopReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopReportController extends Controller
{
    public function __construct(
        private readonly WorkshopReportService $reports,
    ) {}

    public function index(Request $request)
    {
        $user = $this->user($request);
        $filters = $this->filters($request);
        $dateShortcuts = $this->dateShortcuts();
        $workshops = $this->reports
            ->queryFor($user, $filters)
            ->paginate(20)
            ->withQueryString();

        $workshops->getCollection()->transform(
            fn (Workshop $workshop): Workshop => $this->reports->decorate($workshop, $user)
        );

        return response()
            ->view('account.workshop-reports', [
                'workshops' => $workshops,
                'filters' => $filters,
                'filterQuery' => $this->filterQuery($filters),
                'locationOptions' => $this->reports->locationOptionsFor($user),
                'dateShortcuts' => $dateShortcuts,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0, private');
    }

    public function export(Request $request): StreamedResponse
    {
        $user = $this->user($request);
        $filters = $this->filters($request);
        $workshops = $this->reports->queryFor($user, $filters)->get();
        $workshops->each(fn (Workshop $workshop): Workshop => $this->reports->decorate($workshop, $user));

        return response()->streamDownload(function () use ($workshops): void {
            $output = fopen('php://output', 'wb');
            if (! is_resource($output)) {
                return;
            }

            fputcsv($output, ['Workshop', 'Date/Time', 'Location', 'Physical address', 'Attendance', 'Photos']);

            foreach ($workshops as $workshop) {
                fputcsv($output, [
                    $workshop->title,
                    implode(' | ', $workshop->report_schedule),
                    $workshop->report_location_name,
                    $workshop->report_location_address ?? '',
                    $workshop->report_attendance ?? 'NA',
                    collect($workshop->report_photos)->pluck('title')->implode(' | '),
                ]);
            }

            fclose($output);
        }, 'workshop-reports-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
        ]);
    }

    public function downloadAllPhotos(Request $request): BinaryFileResponse
    {
        $user = $this->user($request);
        $filters = $this->filters($request);
        $workshops = $this->reports->queryFor($user, $filters)->get();
        $zipPath = tempnam(sys_get_temp_dir(), 'workshop-report-photos-');

        if ($zipPath === false) {
            abort(500, 'Could not create the photo archive.');
        }

        $zip = new \ZipArchive;
        $usedNames = [];
        $photoCount = 0;

        try {
            if ($zip->open($zipPath, \ZipArchive::OVERWRITE) !== true) {
                abort(500, 'Could not open the photo archive.');
            }

            foreach ($workshops as $workshop) {
                $this->reports->decorate($workshop, $user);

                foreach ($workshop->photos as $photo) {
                    $path = $photo->path();
                    if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
                        continue;
                    }

                    $filename = $this->uniqueZipFilename(
                        $this->photoDownloadFilename($workshop, $photo),
                        $usedNames,
                    );
                    abort_unless($zip->addFile($path, $filename), 500, 'Could not add a photo to the archive.');
                    $photoCount++;

                    try {
                        MediaDownload::query()->create([
                            'media_name' => $photo->name,
                            'user_id' => auth()->id(),
                            'variant' => null,
                            'source' => 'account',
                        ]);
                    } catch (\Throwable $exception) {
                        report($exception);
                    }
                }
            }

            abort_if($photoCount === 0, 404, 'No accessible photos match the selected filters.');
            abort_unless($zip->close(), 500, 'Could not finish the photo archive.');
        } catch (\Throwable $exception) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }

            throw $exception;
        }

        return response()->download($zipPath, 'workshop-report-photos-'.now()->format('Y-m-d').'.zip', [
            'Cache-Control' => 'private, no-store',
        ])->deleteFileAfterSend(true);
    }

    public function photo(Request $request, Workshop $workshop, Media $media)
    {
        $user = $this->user($request);
        abort_unless($this->reports->photoIsAccessible($user, $workshop, $media), 404);

        $variant = (string) $request->query('variant', 'md');
        if (! in_array($variant, ['thumbnail', 'md'], true)) {
            $variant = 'md';
        }

        return $this->photoResponse($workshop, $media, $variant, false);
    }

    public function downloadPhoto(Request $request, Workshop $workshop, Media $media)
    {
        $user = $this->user($request);
        abort_unless($this->reports->photoIsAccessible($user, $workshop, $media), 404);

        return $this->photoResponse($workshop, $media, null, true);
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function photoResponse(Workshop $workshop, Media $media, ?string $variant, bool $download)
    {
        $fileData = $variant === null
            ? [
                'name' => (string) $media->name,
                'mime_type' => (string) $media->mime_type,
                'file' => $media->path(),
            ]
            : $media->getClosestVariant($variant);
        $file = $fileData['file'] ?? null;

        abort_unless(is_string($file) && is_file($file), 404);

        if ($download) {
            try {
                MediaDownload::query()->create([
                    'media_name' => $media->name,
                    'user_id' => auth()->id(),
                    'variant' => null,
                    'source' => 'account',
                ]);
            } catch (\Throwable $exception) {
                report($exception);
            }
        }

        $filename = $download
            ? $this->photoDownloadFilename($workshop, $media)
            : (string) ($fileData['name'] ?? $media->name);

        return response()->file($file, [
            'Content-Type' => (string) ($fileData['mime_type'] ?? $media->mime_type),
            'Content-Disposition' => ($download ? 'attachment; ' : 'inline; ').'filename="'.$filename.'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    private function photoDownloadFilename(Workshop $workshop, Media $media): string
    {
        $photoTitle = trim((string) $media->title);
        if ($photoTitle === '') {
            $photoTitle = pathinfo((string) $media->name, PATHINFO_FILENAME);
        }

        $extension = strtolower((string) pathinfo((string) $media->name, PATHINFO_EXTENSION));
        if ($extension === '') {
            $extension = match (true) {
                str_starts_with(strtolower((string) $media->mime_type), 'video/') => 'mp4',
                default => 'jpg',
            };
        }

        $parts = array_filter([
            $workshop->effectiveStartsAt()?->format('Y-m-d'),
            Str::slug((string) $workshop->title),
            Str::slug($photoTitle),
        ]);

        return implode('-', $parts).'.'.$extension;
    }

    /**
     * @param  array<string, bool>  $usedNames
     */
    private function uniqueZipFilename(string $filename, array &$usedNames): string
    {
        $key = strtolower($filename);
        if (! isset($usedNames[$key])) {
            $usedNames[$key] = true;

            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $base = pathinfo($filename, PATHINFO_FILENAME);
        $counter = 2;
        do {
            $candidate = $base.'-'.$counter.($extension !== '' ? '.'.$extension : '');
            $counter++;
        } while (isset($usedNames[strtolower($candidate)]));

        $usedNames[strtolower($candidate)] = true;

        return $candidate;
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'location' => ['nullable', 'string', 'max:64'],
            'photos' => ['nullable', Rule::in(['all', 'with', 'without'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
            'workshop_id' => ['nullable', 'string', 'max:64'],
        ]);

        $dateFrom = trim((string) ($validated['date_from'] ?? ''));
        $dateTo = trim((string) ($validated['date_to'] ?? ''));

        if ($dateFrom === '' && $dateTo === '') {
            $quarterStart = now()->startOfQuarter();
            $dateFrom = $quarterStart->toDateString();
            $dateTo = $quarterStart->copy()->endOfQuarter()->toDateString();
        }

        return [
            'search' => trim((string) ($validated['search'] ?? '')),
            'location' => trim((string) ($validated['location'] ?? '')),
            'photos' => (string) ($validated['photos'] ?? 'all'),
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'workshop_id' => trim((string) ($validated['workshop_id'] ?? '')),
        ];
    }

    /**
     * @return array<string, array{label: string, from: string, to: string}>
     */
    private function dateShortcuts(): array
    {
        $today = now();
        $quarterStart = $today->copy()->startOfQuarter();
        $quarterEnd = $quarterStart->copy()->endOfQuarter();

        $halfYearStart = $today->month <= 6
            ? $today->copy()->startOfYear()
            : $today->copy()->startOfYear()->addMonths(6);
        $halfYearEnd = $halfYearStart->copy()->addMonths(6)->subDay();
        $halfYearLabel = $today->month <= 6
            ? 'Current half-year · Jan–Jun '.$today->format('Y')
            : 'Current half-year · Jul–Dec '.$today->format('Y');

        $financialYearStart = $today->month >= 7
            ? $today->copy()->startOfYear()->addMonths(6)
            : $today->copy()->subYear()->startOfYear()->addMonths(6);
        $financialYearEnd = $financialYearStart->copy()->addYear()->subDay();

        return [
            'quarter' => [
                'label' => 'Current quarter · '.$quarterStart->format('j M').'–'.$quarterEnd->format('j M Y'),
                'from' => $quarterStart->toDateString(),
                'to' => $quarterEnd->toDateString(),
            ],
            'half_year' => [
                'label' => $halfYearLabel,
                'from' => $halfYearStart->toDateString(),
                'to' => $halfYearEnd->toDateString(),
            ],
            'financial_year' => [
                'label' => 'Financial year · '.$financialYearStart->format('Y').'–'.$financialYearEnd->format('y'),
                'from' => $financialYearStart->toDateString(),
                'to' => $financialYearEnd->toDateString(),
            ],
        ];
    }

    /**
     * @param  array<string, string>  $filters
     * @return array<string, string>
     */
    private function filterQuery(array $filters): array
    {
        return array_filter([
            'search' => $filters['search'] ?? '',
            'location' => $filters['location'] ?? '',
            'photos' => ($filters['photos'] ?? 'all') !== 'all' ? ($filters['photos'] ?? '') : '',
            'date_from' => $filters['date_from'] ?? '',
            'date_to' => $filters['date_to'] ?? '',
        ], fn (string $value): bool => $value !== '');
    }
}
