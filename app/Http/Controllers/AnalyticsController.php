<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\AnalyticsEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AnalyticsController extends Controller
{
    public function index(Request $request): View
    {
        $days = (int) $request->query('days', 30);
        if (! in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        $from = now()->subDays($days)->startOfDay();
        $baseQuery = AnalyticsEvent::query()->where('analytics_events.created_at', '>=', $from);

        $totals = [
            'views' => (clone $baseQuery)->count(),
            'sessions' => (clone $baseQuery)->distinct('session_token')->count('session_token'),
            'visitors' => (clone $baseQuery)->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
        ];

        $dailyFrom = now()->subDays(7)->startOfDay();
        $daily = AnalyticsEvent::query()
            ->where('analytics_events.created_at', '>=', $dailyFrom)
            ->selectRaw('DATE(analytics_events.created_at) as day, COUNT(*) as views, COUNT(DISTINCT analytics_events.session_token) as sessions')
            ->groupBy(DB::raw('DATE(analytics_events.created_at)'))
            ->orderByDesc(DB::raw('DATE(analytics_events.created_at)'))
            ->paginate(7, ['*'], 'daily_page')
            ->onEachSide(1);

        $hoursFrom = now()->subHours(12);
        $activeHours = AnalyticsEvent::query()
            ->where('analytics_events.created_at', '>=', $hoursFrom)
            ->selectRaw("
                DATE_FORMAT(analytics_events.created_at, '%Y-%m-%d %H:00:00') as hour_bucket,
                COUNT(*) as views,
                COUNT(DISTINCT analytics_events.session_token) as sessions,
                COUNT(DISTINCT COALESCE(NULLIF(analytics_events.visitor_hash, ''), CONCAT('session:', analytics_events.session_token))) as users
            ")
            ->groupBy(DB::raw("DATE_FORMAT(analytics_events.created_at, '%Y-%m-%d %H:00:00')"))
            ->orderByDesc('hour_bucket')
            ->paginate(12, ['*'], 'hour_page')
            ->onEachSide(1);

        $topPages = (clone $baseQuery)
            ->selectRaw('analytics_events.path as path, COUNT(*) as views, COUNT(DISTINCT analytics_events.session_token) as sessions')
            ->groupBy('analytics_events.path')
            ->orderByDesc('views')
            ->paginate(10, ['*'], 'top_pages_page')
            ->onEachSide(1);

        $topWorkshops = (clone $baseQuery)
            ->whereNotNull('analytics_events.workshop_id')
            ->leftJoin('workshops', 'workshops.id', '=', 'analytics_events.workshop_id')
            ->leftJoin('locations', 'locations.id', '=', 'workshops.location_id')
            ->selectRaw('
                analytics_events.workshop_id,
                COALESCE(workshops.title, analytics_events.workshop_id) as workshop_title,
                workshops.starts_at as workshop_starts_at,
                workshops.location_id as workshop_location_id,
                locations.name as workshop_location_name,
                COUNT(*) as views,
                COUNT(DISTINCT analytics_events.session_token) as sessions
            ')
            ->groupBy(
                'analytics_events.workshop_id',
                'workshops.title',
                'workshops.starts_at',
                'workshops.location_id',
                'locations.name'
            )
            ->orderByDesc('views')
            ->paginate(10, ['*'], 'top_workshops_page')
            ->onEachSide(1);

        $topSearches = (clone $baseQuery)
            ->whereNotNull('analytics_events.search_term')
            ->where('analytics_events.search_term', '!=', '')
            ->selectRaw('analytics_events.search_term as search_term, COUNT(*) as uses, COUNT(DISTINCT analytics_events.session_token) as sessions')
            ->groupBy('analytics_events.search_term')
            ->orderByDesc('uses')
            ->paginate(10, ['*'], 'top_searches_page')
            ->onEachSide(1);

        $recentSessions = (clone $baseQuery)
            ->selectRaw('analytics_events.session_token as session_token, MAX(analytics_events.visitor_hash) as visitor_hash, MIN(analytics_events.created_at) as started_at, MAX(analytics_events.created_at) as ended_at, COUNT(*) as event_count')
            ->groupBy('analytics_events.session_token')
            ->orderByDesc(DB::raw('MAX(analytics_events.created_at)'))
            ->paginate(10, ['*'], 'session_flows_page')
            ->onEachSide(1);

        $sessionTokens = $recentSessions->getCollection()->pluck('session_token')->all();
        $sessionEvents = collect();
        if ($sessionTokens !== []) {
            $sessionEvents = AnalyticsEvent::query()
                ->whereIn('session_token', $sessionTokens)
                ->orderBy('created_at')
                ->get(['session_token', 'event_type', 'path', 'search_term', 'created_at'])
                ->groupBy('session_token');
        }

        $sessionFlowRows = $recentSessions->getCollection()->map(function ($session) use ($sessionEvents) {
            $events = collect($sessionEvents->get($session->session_token, []));

            $steps = [];
            foreach ($events as $event) {
                $label = (string) $event->path;
                if ($event->event_type === AnalyticsEvent::TYPE_SEARCH && trim((string) $event->search_term) !== '') {
                    $label .= ' (search: '.trim((string) $event->search_term).')';
                }

                $last = end($steps);
                if ($last !== $label) {
                    $steps[] = $label;
                }
            }

            return [
                'session_token' => (string) $session->session_token,
                'visitor_hash' => (string) ($session->visitor_hash ?? ''),
                'started_at' => $session->getAttribute('started_at'),
                'ended_at' => $session->getAttribute('ended_at'),
                'event_count' => (int) $session->getAttribute('event_count'),
                'steps' => array_slice($steps, 0, 12),
            ];
        });

        $sessionFlows = new LengthAwarePaginator(
            $sessionFlowRows,
            $recentSessions->total(),
            $recentSessions->perPage(),
            $recentSessions->currentPage(),
            [
                'path' => request()->url(),
                'query' => request()->query(),
                'pageName' => $recentSessions->getPageName(),
            ]
        );

        $returningVisitors = (clone $baseQuery)
            ->whereNotNull('analytics_events.visitor_hash')
            ->selectRaw('analytics_events.visitor_hash as visitor_hash, COUNT(*) as views, COUNT(DISTINCT analytics_events.session_token) as sessions, MAX(analytics_events.created_at) as last_seen')
            ->groupBy('analytics_events.visitor_hash')
            ->orderByDesc('sessions')
            ->orderByDesc('views')
            ->paginate(10, ['*'], 'returning_visitors_page')
            ->onEachSide(1);

        $totalRecords = AnalyticsEvent::query()->count();
        $oldestRecordAt = AnalyticsEvent::query()->min('created_at');
        $tableSizeBytes = $this->analyticsTableSizeBytes();
        $tableSizeHuman = $tableSizeBytes !== null ? Helpers::bytesToString($tableSizeBytes) : null;

        return view('admin.analytics.index', [
            'days' => $days,
            'totals' => $totals,
            'analyticsMeta' => [
                'table_size_bytes' => $tableSizeBytes,
                'table_size_human' => $tableSizeHuman,
                'oldest_record_at' => $oldestRecordAt,
                'total_records' => $totalRecords,
            ],
            'daily' => $daily,
            'activeHours' => $activeHours,
            'topPages' => $topPages,
            'topWorkshops' => $topWorkshops,
            'topSearches' => $topSearches,
            'sessionFlows' => $sessionFlows,
            'returningVisitors' => $returningVisitors,
        ]);
    }

    public function prune(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prune_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'days' => ['nullable', 'integer'],
        ]);

        $pruneDays = (int) $validated['prune_days'];
        $cutoff = now()->subDays($pruneDays)->startOfDay();
        $deleted = AnalyticsEvent::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        session()->flash('message', 'Pruned '.number_format((int) $deleted).' analytics records older than '.$pruneDays.' days.');
        session()->flash('message-title', 'Analytics pruned');
        session()->flash('message-type', 'success');

        $days = (int) ($validated['days'] ?? 30);
        if (! in_array($days, [7, 30, 90, 365], true)) {
            $days = 30;
        }

        return redirect()->route('admin.analytics.index', ['days' => $days]);
    }

    private function analyticsTableSizeBytes(): ?int
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $size = DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'analytics_events')
                ->selectRaw('COALESCE(data_length, 0) + COALESCE(index_length, 0) AS total_bytes')
                ->value('total_bytes');

            return is_numeric($size) ? (int) $size : null;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne("SELECT pg_total_relation_size('analytics_events') AS total_bytes");

            return is_object($row) && isset($row->total_bytes) && is_numeric($row->total_bytes)
                ? (int) $row->total_bytes
                : null;
        }

        return null;
    }
}
