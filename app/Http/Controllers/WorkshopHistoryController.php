<?php

namespace App\Http\Controllers;

use App\Models\Location;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopCategory;
use Barryvdh\DomPDF\Facade\Pdf as DomPdf;
use Barryvdh\DomPDF\PDF;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WorkshopHistoryController extends Controller
{
    public function index(Request $request): View
    {
        $query = $this->filteredQuery($request);

        return view('admin.workshop.history', [
            'workshops' => $query->paginate(25)->withQueryString(),
            ...$this->filterOptions(),
        ]);
    }

    public function csv(Request $request): StreamedResponse
    {
        $filename = 'workshop-history-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($request): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Date', 'Workshop', 'Hosted for', 'Requested by', 'Location', 'Status']);
            $this->filteredQuery($request)->chunk(200, function (Collection $workshops) use ($output): void {
                foreach ($workshops as $workshop) {
                    fputcsv($output, [
                        $workshop->starts_at?->format('Y-m-d H:i'),
                        $workshop->title,
                        $workshop->hostedFor?->name,
                        $workshop->requestedBy?->getName(),
                        $workshop->getLocationName(),
                        $workshop->adminStatusLabel(),
                    ]);
                }
            });
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function pdf(Request $request): Response
    {
        $workshops = $this->filteredQuery($request)->get();

        return $this->pdfResponse(
            DomPdf::loadView('pdf.workshop-history', [
                'workshops' => $workshops,
                'generatedAt' => now(),
                'filters' => $this->filterSummary($request, $workshops->count()),
                'showStatus' => $request->boolean('include_cancelled'),
            ])->setPaper('a4', 'portrait'),
            'workshop-history-'.now()->format('Y-m-d').'.pdf'
        );
    }

    public function matrix(Request $request): View
    {
        return view('admin.workshop.coverage', [
            ...$this->matrixData($request),
            ...$this->filterOptions(),
        ]);
    }

    public function matrixCsv(Request $request): StreamedResponse
    {
        $matrix = $this->matrixData($request);

        return response()->streamDownload(function () use ($matrix): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Workshop', ...$matrix['columns']->map(
                fn (array $column): string => $column['organisation_name'].' — '.$column['location_name']
            )->all()]);
            foreach ($matrix['rows'] as $row) {
                fputcsv($output, [
                    $row['title'],
                    ...$matrix['columns']->map(fn (array $column): string => collect($row['cells'][$column['id']] ?? [])
                        ->pluck('date')
                        ->implode(' | '))->all(),
                ]);
            }
            fclose($output);
        }, 'workshop-coverage-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }

    public function matrixPdf(Request $request): Response
    {
        $matrix = $this->matrixData($request);

        return $this->pdfResponse(
            DomPdf::loadView('pdf.workshop-coverage', [
                ...$matrix,
                'generatedAt' => now(),
                'filters' => $this->filterSummary($request, $matrix['workshopCount']),
            ])->setPaper('a4', 'portrait'),
            'workshop-coverage-'.now()->format('Y-m-d').'.pdf'
        );
    }

    /**
     * @return Builder<Workshop>
     */
    private function filteredQuery(Request $request): Builder
    {
        $search = trim((string) $request->query('search', ''));
        $organisationId = trim((string) $request->query('organisation_id', ''));
        $organisationIds = $this->requestIds($request, 'organisation_ids');
        $locationIds = $this->requestIds($request, 'location_ids', 'location_id');
        $requestedByUserIds = $this->requestIds($request, 'requested_by_user_ids', 'requested_by_user_id');
        $categoryIds = $this->requestIds($request, 'category_ids', 'category_id');
        if ($organisationIds === [] && $organisationId !== '') {
            $organisationIds = [$organisationId];
        }
        if ($organisationIds !== [] && $request->boolean('include_children')) {
            $organisationIds = Organisation::query()
                ->whereIn('id', $organisationIds)
                ->get()
                ->flatMap(fn (Organisation $organisation): array => [(string) $organisation->id, ...$organisation->descendantIds()])
                ->unique()
                ->values()
                ->all();
        }

        return Workshop::query()
            ->with(['hostedFor', 'requestedBy', 'location', 'categories'])
            ->when($request->boolean('past_only'), fn ($query) => $query->where('starts_at', '<=', now()))
            ->when(! $request->boolean('include_cancelled'), fn ($query) => $query->whereNotIn('status', ['draft', 'cancelled']))
            ->when($locationIds !== [], fn ($query) => $query->whereIn('location_id', $locationIds))
            ->when($organisationIds !== [], fn ($query) => $query->whereIn('hosted_for_organisation_id', $organisationIds))
            ->when($requestedByUserIds !== [], fn ($query) => $query->whereIn('requested_by_user_id', $requestedByUserIds))
            ->when($categoryIds !== [], fn ($query) => $query->whereHas('categories', fn ($query) => $query->whereIn('workshop_categories.id', $categoryIds)))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('starts_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('starts_at', '<=', $request->query('date_to')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('location', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('hostedFor', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('requestedBy', fn ($query) => $query
                        ->where('firstname', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhereHas('primaryOrganisation', fn ($query) => $query->where('name', 'like', '%'.$search.'%')));
            }))
            ->orderByDesc('starts_at');
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'locations' => Location::query()->orderBy('name')->get(),
            'organisations' => Organisation::query()->with('parent')->orderBy('name')->get(),
            'contacts' => User::query()->whereHas('requestedWorkshops')->orderBy('firstname')->orderBy('surname')->get(),
            'categories' => WorkshopCategory::query()->orderBy('name')->get(),
        ];
    }

    /**
     * @return array{
     *     columns: SupportCollection<int, array{id: string, organisation_id: string, organisation_name: string, location_name: string}>,
     *     columnGroups: SupportCollection<int, array{id: string, name: string, columns: SupportCollection<int, array{id: non-falsy-string, organisation_id: string, organisation_name: string, location_name: string}>}>,
     *     rows: SupportCollection<int, array{title: string, cells: array<non-falsy-string, non-empty-list<array{date: string, edit_url: string}>>}>,
     *     workshopCount: int
     * }
     */
    private function matrixData(Request $request): array
    {
        $requestedOrganisationIds = $request->query('organisation_ids', []);
        $organisationIds = is_array($requestedOrganisationIds)
            ? array_values(array_filter(array_map('strval', $requestedOrganisationIds)))
            : [];
        if ($organisationIds === [] && $request->filled('organisation_id')) {
            $organisationIds = [(string) $request->query('organisation_id')];
        }

        if ($organisationIds === []) {
            return [
                'columns' => collect(),
                'columnGroups' => collect(),
                'rows' => collect(),
                'workshopCount' => 0,
            ];
        }

        $workshops = $this->filteredQuery($request)->get();
        $organisations = Organisation::query()
            ->whereIn('id', $organisationIds)
            ->orderBy('name')
            ->get();

        $columnGroups = $organisations->map(function (Organisation $organisation) use ($workshops): array {
            $organisationId = (string) $organisation->id;
            $organisationWorkshops = $workshops
                ->filter(fn (Workshop $workshop): bool => (string) $workshop->hosted_for_organisation_id === $organisationId);

            $locationColumns = $organisationWorkshops
                ->groupBy(fn (Workshop $workshop): string => $this->matrixLocationKey($workshop))
                ->map(function (Collection $locationWorkshops, string $locationKey) use ($organisation, $organisationId): array {
                    $workshop = $locationWorkshops->first();

                    return [
                        'id' => $organisationId.'|'.$locationKey,
                        'organisation_id' => $organisationId,
                        'organisation_name' => (string) $organisation->name,
                        'location_name' => $workshop instanceof Workshop ? $workshop->getLocationName() : 'No location',
                    ];
                })
                ->sortBy('location_name', SORT_NATURAL | SORT_FLAG_CASE)
                ->values();

            if ($locationColumns->isEmpty()) {
                $locationColumns->push([
                    'id' => $organisationId.'|__none',
                    'organisation_id' => $organisationId,
                    'organisation_name' => (string) $organisation->name,
                    'location_name' => '-',
                ]);
            }

            return [
                'id' => $organisationId,
                'name' => (string) $organisation->name,
                'columns' => $locationColumns,
            ];
        })->values();

        $columns = $columnGroups->flatMap(fn (array $group) => $group['columns'])->values();

        $rows = $workshops
            ->groupBy(fn (Workshop $workshop): string => mb_strtolower(trim((string) $workshop->title)))
            ->map(function (Collection $group): array {
                $cells = [];
                foreach ($group->sortBy('starts_at') as $workshop) {
                    $columnId = $workshop->hostedFor instanceof Organisation
                        ? (string) $workshop->hostedFor->id.'|'.$this->matrixLocationKey($workshop)
                        : '__unassigned';
                    $cells[$columnId][] = [
                        'date' => (string) ($workshop->starts_at?->format('d M Y') ?? 'No date'),
                        'edit_url' => route('admin.workshop.edit', $workshop),
                    ];
                }

                return [
                    'title' => (string) $group->first()->title,
                    'cells' => array_map(
                        fn (array $deliveries): array => $deliveries,
                        $cells
                    ),
                ];
            })
            ->sortBy('title', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        return [
            'columns' => $columns,
            'columnGroups' => $columnGroups,
            'rows' => $rows,
            'workshopCount' => $workshops->count(),
        ];
    }

    private function matrixLocationKey(Workshop $workshop): string
    {
        $locationId = trim((string) ($workshop->location_id ?? ''));

        return $locationId !== '' ? $locationId : '__'.$workshop->locationType();
    }

    /**
     * @return array<int, string>
     */
    private function filterSummary(Request $request, int $workshopCount): array
    {
        $clauses = [];
        if ($request->filled('search')) {
            $clauses[] = 'matching “'.trim((string) $request->query('search')).'”';
        }
        $organisationIds = $this->requestIds($request, 'organisation_ids', 'organisation_id');
        if ($organisationIds !== []) {
            $names = Organisation::query()->whereIn('id', $organisationIds)->orderBy('name')->pluck('name')->all();
            if ($names !== []) {
                $clauses[] = 'hosted for '.implode(', ', $names)
                    .($request->boolean('include_children') ? ' and their child organisations' : '');
            }
        }
        $requestedByUserIds = $this->requestIds($request, 'requested_by_user_ids', 'requested_by_user_id');
        if ($requestedByUserIds !== []) {
            $names = User::query()->whereIn('id', $requestedByUserIds)->get()->map->getName()->filter()->all();
            if ($names !== []) {
                $clauses[] = 'requested by '.implode(', ', $names);
            }
        }
        $locationIds = $this->requestIds($request, 'location_ids', 'location_id');
        if ($locationIds !== []) {
            $names = Location::query()->whereIn('id', $locationIds)->orderBy('name')->pluck('name')->all();
            if ($names !== []) {
                $clauses[] = 'at '.implode(', ', $names);
            }
        }
        $categoryIds = $this->requestIds($request, 'category_ids', 'category_id');
        if ($categoryIds !== []) {
            $names = WorkshopCategory::query()->whereIn('id', $categoryIds)->orderBy('name')->pluck('name')->all();
            if ($names !== []) {
                $clauses[] = 'in at least one of the '.implode(', ', $names).' categories';
            }
        }
        if ($request->filled('date_from') && $request->filled('date_to')) {
            $clauses[] = 'between '.Carbon::parse((string) $request->query('date_from'))->format('d M Y')
                .' and '.Carbon::parse((string) $request->query('date_to'))->format('d M Y');
        } elseif ($request->filled('date_from')) {
            $clauses[] = 'from '.Carbon::parse((string) $request->query('date_from'))->format('d M Y');
        } elseif ($request->filled('date_to')) {
            $clauses[] = 'up to '.Carbon::parse((string) $request->query('date_to'))->format('d M Y');
        }

        $summary = 'Showing '.number_format($workshopCount).' '
            .($request->boolean('past_only') ? 'past ' : '')
            .($workshopCount === 1 ? 'workshop' : 'workshops');
        if ($clauses !== []) {
            $summary .= ' '.implode(' and ', $clauses);
        }
        if ($request->boolean('include_cancelled')) {
            $summary .= ', including cancelled workshops and drafts';
        }

        return [$summary];
    }

    /**
     * @return array<int, string>
     */
    private function requestIds(Request $request, string $arrayKey, ?string $legacyKey = null): array
    {
        $values = $request->query($arrayKey, []);
        $ids = is_array($values) ? $values : [];

        if ($ids === [] && $legacyKey !== null && $request->filled($legacyKey)) {
            $ids = [$request->query($legacyKey)];
        }

        return collect($ids)
            ->map(fn ($id): string => trim((string) $id))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    private function pdfResponse(PDF $pdf, string $filename): Response
    {
        $pdf->render();
        $dompdf = $pdf->getDomPDF();
        $canvas = $dompdf->getCanvas();
        $font = $dompdf->getFontMetrics()->getFont('DejaVu Sans', 'normal');
        $canvas->page_text(
            $canvas->get_width() - 92,
            $canvas->get_height() - 24,
            'Page {PAGE_NUM} of {PAGE_COUNT}',
            $font,
            9,
            [0, 0, 0]
        );

        return $pdf->stream($filename);
    }
}
