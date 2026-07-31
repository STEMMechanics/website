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
            fputcsv($output, ['Workshop', ...$matrix['columns']->pluck('name')->all()]);
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
            ])->setPaper('a3', 'portrait'),
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
        $requestedOrganisationIds = $request->query('organisation_ids', []);
        $organisationIds = is_array($requestedOrganisationIds)
            ? array_values(array_filter(array_map('strval', $requestedOrganisationIds)))
            : [];
        if ($organisationIds === [] && $organisationId !== '') {
            $organisationIds = [$organisationId];
        }
        if ($organisationId !== '' && $request->boolean('include_children')) {
            $organisation = Organisation::query()->find($organisationId);
            if ($organisation) {
                $organisationIds = [$organisationId, ...$organisation->descendantIds()];
            }
        }

        return Workshop::query()
            ->with(['hostedFor', 'requestedBy', 'location', 'categories'])
            ->when($request->boolean('past_only'), fn ($query) => $query->where('starts_at', '<=', now()))
            ->when(! $request->boolean('include_cancelled'), fn ($query) => $query->whereNotIn('status', ['draft', 'cancelled']))
            ->when($request->filled('location_id'), fn ($query) => $query->where('location_id', $request->query('location_id')))
            ->when($organisationIds !== [], fn ($query) => $query->whereIn('hosted_for_organisation_id', $organisationIds))
            ->when($request->filled('requested_by_user_id'), fn ($query) => $query->where('requested_by_user_id', $request->query('requested_by_user_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->whereHas('categories', fn ($query) => $query->whereKey($request->query('category_id'))))
            ->when($request->filled('date_from'), fn ($query) => $query->whereDate('starts_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn ($query) => $query->whereDate('starts_at', '<=', $request->query('date_to')))
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search): void {
                $query->where('title', 'like', '%'.$search.'%')
                    ->orWhereHas('location', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('hostedFor', fn ($query) => $query->where('name', 'like', '%'.$search.'%'))
                    ->orWhereHas('requestedBy', fn ($query) => $query
                        ->where('firstname', 'like', '%'.$search.'%')
                        ->orWhere('surname', 'like', '%'.$search.'%')
                        ->orWhere('company', 'like', '%'.$search.'%'));
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
     * @return array{columns: SupportCollection<int, array{id: string, name: string}>, rows: SupportCollection<int, array{title: string, cells: array<string, non-empty-list<array{date: string, edit_url: string}>>}>, workshopCount: int}
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
                'rows' => collect(),
                'workshopCount' => 0,
            ];
        }

        $workshops = $this->filteredQuery($request)->get();
        $columns = Organisation::query()
            ->whereIn('id', $organisationIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Organisation $organisation): array => [
                'id' => (string) $organisation->id,
                'name' => (string) $organisation->name,
            ])
            ->values();

        $rows = $workshops
            ->groupBy(fn (Workshop $workshop): string => mb_strtolower(trim((string) $workshop->title)))
            ->map(function (Collection $group): array {
                $cells = [];
                foreach ($group->sortBy('starts_at') as $workshop) {
                    $columnId = $workshop->hostedFor instanceof Organisation
                        ? (string) $workshop->hostedFor->id
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
            'rows' => $rows,
            'workshopCount' => $workshops->count(),
        ];
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
        if ($request->filled('organisation_id')) {
            $organisation = Organisation::query()->find($request->query('organisation_id'));
            if ($organisation) {
                $clauses[] = 'hosted for '.$organisation->name
                    .($request->boolean('include_children') ? ' and its child organisations' : '');
            }
        }
        if ($request->filled('requested_by_user_id')) {
            $contact = User::query()->find($request->query('requested_by_user_id'));
            if ($contact) {
                $clauses[] = 'requested by '.$contact->getName();
            }
        }
        if ($request->filled('location_id')) {
            $location = Location::query()->find($request->query('location_id'));
            if ($location) {
                $clauses[] = 'at '.$location->name;
            }
        }
        if ($request->filled('category_id')) {
            $category = WorkshopCategory::query()->find($request->query('category_id'));
            if ($category) {
                $clauses[] = 'in the '.$category->name.' category';
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
