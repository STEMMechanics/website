<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\EmailSubscriptions;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminDashboardService
{
    private const PERIODS = [
        'overview' => ['label' => 'Overview'],
        'day' => ['label' => 'This day'],
        'week' => ['label' => 'This week'],
        'month' => ['label' => 'This month'],
        'quarter' => ['label' => 'This quarter'],
        'year' => ['label' => 'This year'],
    ];

    private const INTERNAL_WORKSHOP_REGISTRATIONS = ['tickets'];

    public function build(string $period = 'overview'): array
    {
        $periodKey = array_key_exists($period, self::PERIODS) ? $period : 'overview';
        $periodConfig = self::PERIODS[$periodKey];

        $currentWindow = $this->periodWindow($periodKey, now());
        $currentStart = $currentWindow['start'];
        $currentEnd = $currentWindow['end'];
        $previousWindow = $this->periodWindow($periodKey, (clone $currentStart)->subSecond());
        $previousStart = $previousWindow['start'];
        $previousEnd = $previousWindow['end'];

        $workshopViewsCurrent = $this->countAnalyticsEventsForRoutesBetween($currentStart, $currentEnd, ['workshop.index', 'workshop.show']);
        $workshopViewsPrevious = $this->countAnalyticsEventsForRoutesBetween($previousStart, $previousEnd, ['workshop.index', 'workshop.show']);
        $ticketsSoldCurrent = $this->countWorkshopTicketSalesBetween($currentStart, $currentEnd);
        $ticketsSoldPrevious = $this->countWorkshopTicketSalesBetween($previousStart, $previousEnd);
        $externalRegistrationClicksCurrent = $this->countExternalRegistrationClickersBetween($currentStart, $currentEnd);
        $externalRegistrationClicksPrevious = $this->countExternalRegistrationClickersBetween($previousStart, $previousEnd);

        $incomeGrossCurrent = $this->sumPaymentsBetween($currentStart, $currentEnd, Payment::KIND_PAYMENT);
        $incomeGrossPrevious = $this->sumPaymentsBetween($previousStart, $previousEnd, Payment::KIND_PAYMENT);
        $refundsCurrent = $this->sumPaymentsBetween($currentStart, $currentEnd, Payment::KIND_REFUND);
        $refundsPrevious = $this->sumPaymentsBetween($previousStart, $previousEnd, Payment::KIND_REFUND);
        $expensesCurrent = $this->sumExpensesBetween($currentStart, $currentEnd);
        $expensesPrevious = $this->sumExpensesBetween($previousStart, $previousEnd);
        $profitCurrent = round($incomeGrossCurrent - $refundsCurrent - $expensesCurrent, 2);
        $profitPrevious = round($incomeGrossPrevious - $refundsPrevious - $expensesPrevious, 2);

        $storeViewsCurrent = $this->countAnalyticsEventsForRoutesBetween($currentStart, $currentEnd, ['shop.index']);
        $storeViewsPrevious = $this->countAnalyticsEventsForRoutesBetween($previousStart, $previousEnd, ['shop.index']);
        $storeItemViewsCurrent = $this->countAnalyticsEventsForRoutesBetween($currentStart, $currentEnd, ['shop.product.show']);
        $storeItemViewsPrevious = $this->countAnalyticsEventsForRoutesBetween($previousStart, $previousEnd, ['shop.product.show']);
        $storeItemsSoldCurrent = $this->countStoreItemsSoldBetween($currentStart, $currentEnd);
        $storeItemsSoldPrevious = $this->countStoreItemsSoldBetween($previousStart, $previousEnd);

        $analyticsViewsCurrent = $this->countAnalyticsEventsBetween($currentStart, $currentEnd);
        $analyticsViewsPrevious = $this->countAnalyticsEventsBetween($previousStart, $previousEnd);
        $analyticsVisitorsCurrent = $this->countAnalyticsVisitorsBetween($currentStart, $currentEnd);
        $analyticsVisitorsPrevious = $this->countAnalyticsVisitorsBetween($previousStart, $previousEnd);

        $totalUsersCurrent = $this->countUsersAt($currentEnd);
        $totalUsersPrevious = $this->countUsersAt($previousEnd);
        $totalSubscriptionsCurrent = $this->countSubscriptionsAt($currentEnd);
        $totalSubscriptionsPrevious = $this->countSubscriptionsAt($previousEnd);

        $workshopSalesRows = $this->topWorkshopSalesRows($currentStart, $currentEnd);
        $storeSalesRows = $this->topStoreSalesRows($currentStart, $currentEnd);
        $trafficSourceRows = $this->topTrafficSourceRows($currentStart, $currentEnd);
        $chartBuckets = $this->chartBuckets($periodKey, $currentStart, $currentEnd);

        return [
            'period' => $periodKey,
            'periodLabel' => $periodConfig['label'],
            'periodStart' => $currentStart,
            'periodEnd' => $currentEnd,
            'cards' => [
                [
                    'title' => 'Workshops',
                    'description' => 'Workshop page views in the selected period.',
                    'links' => [
                        ['label' => 'Workshops', 'route' => route('admin.workshop.index'), 'icon' => 'fa-solid fa-bullhorn'],
                        ['label' => 'Tickets', 'route' => route('admin.ticket.index'), 'icon' => 'fa-solid fa-ticket'],
                    ],
                    'metrics' => [
                        $this->metric('Workshop views', $workshopViewsCurrent, $workshopViewsPrevious),
                    ],
                ],
                [
                    'title' => 'Tickets',
                    'description' => 'Internal ticket sales and visits to external ticketing systems.',
                    'links' => [
                        ['label' => 'Tickets', 'route' => route('admin.ticket.index'), 'icon' => 'fa-solid fa-ticket'],
                    ],
                    'metrics' => [
                        $this->metric('Tickets sold', $ticketsSoldCurrent, $ticketsSoldPrevious),
                        $this->metric('Unique external registration clicks', $externalRegistrationClicksCurrent, $externalRegistrationClicksPrevious),
                    ],
                ],
                [
                    'title' => 'Store',
                    'description' => 'Store page and product views in the selected period.',
                    'links' => [
                        ['label' => 'Products', 'route' => route('admin.shop.product.index'), 'icon' => 'fa-solid fa-box'],
                        ['label' => 'Orders', 'route' => route('admin.shop.order.index'), 'icon' => 'fa-solid fa-receipt'],
                    ],
                    'metrics' => [
                        $this->metric('Store views', $storeViewsCurrent, $storeViewsPrevious),
                        $this->metric('Product views', $storeItemViewsCurrent, $storeItemViewsPrevious),
                        $this->metric('Items sold', $storeItemsSoldCurrent, $storeItemsSoldPrevious),
                    ],
                ],
                [
                    'title' => 'Finance',
                    'description' => 'Income, refunds, expenses and profit during the selected period.',
                    'links' => [
                        ['label' => 'BAS', 'route' => route('admin.bas.index'), 'icon' => 'fa-solid fa-calculator'],
                        ['label' => 'Expenses', 'route' => route('admin.expense.index'), 'icon' => 'fa-solid fa-receipt'],
                        ['label' => 'Invoices', 'route' => route('admin.invoice.index'), 'icon' => 'fa-solid fa-file-invoice-dollar'],
                    ],
                    'metrics' => [
                        $this->moneyMetric('Profit', $profitCurrent, $profitPrevious),
                        $this->moneyMetric('Income', $incomeGrossCurrent, $incomeGrossPrevious),
                        $this->moneyMetric('Expenses', $expensesCurrent, $expensesPrevious, false),
                        $this->moneyMetric('Refunds', $refundsCurrent, $refundsPrevious, false),
                    ],
                ],
                [
                    'title' => 'Website',
                    'description' => 'Analytics events captured for the selected period.',
                    'links' => [
                        ['label' => 'Analytics', 'route' => route('admin.analytics.index'), 'icon' => 'fa-solid fa-chart-line'],
                    ],
                    'metrics' => [
                        $this->metric('Page views', $analyticsViewsCurrent, $analyticsViewsPrevious),
                        $this->metric('Unique visitors', $analyticsVisitorsCurrent, $analyticsVisitorsPrevious),
                    ],
                ],
                [
                    'title' => 'Growth',
                    'description' => 'Total verified users and confirmed email subscriptions.',
                    'links' => [
                        ['label' => 'Users', 'route' => route('admin.user.index'), 'icon' => 'fa-solid fa-users'],
                        ['label' => 'Subscriptions', 'route' => route('admin.subscription.index'), 'icon' => 'fa-solid fa-envelope-open-text'],
                    ],
                    'metrics' => [
                        $this->metric('Total users', $totalUsersCurrent, $totalUsersPrevious),
                        $this->metric('Total subscriptions', $totalSubscriptionsCurrent, $totalSubscriptionsPrevious),
                    ],
                ],
            ],
            'charts' => [
                $this->workshopsChart($chartBuckets),
                $this->ticketsChart($chartBuckets),
                $this->storeChart($chartBuckets),
                $this->websiteTrafficChart($chartBuckets),
                $this->financeChart($chartBuckets),
                $this->growthChart($chartBuckets),
            ],
            'workshopSalesRows' => $workshopSalesRows,
            'storeSalesRows' => $storeSalesRows,
            'trafficSourceRows' => $trafficSourceRows,
        ];
    }

    private function topTrafficSourceRows(Carbon $start, Carbon $end): Collection
    {
        $sessionEntries = DB::table('analytics_events as session_events')
            ->selectRaw('MIN(session_events.id) as entry_id')
            ->groupBy('session_events.session_token');
        $attributionSql = $this->attributionSqlExpressions();

        $attributedSessions = AnalyticsEvent::query()
            ->joinSub($sessionEntries, 'session_entries', fn ($join) => $join->on('session_entries.entry_id', '=', 'analytics_events.id'))
            ->where('analytics_events.created_at', '>=', $start)
            ->where('analytics_events.created_at', '<', $end)
            ->selectRaw($attributionSql['source'].' as source', $attributionSql['bindings'])
            ->selectRaw($attributionSql['medium'].' as medium', $attributionSql['bindings'])
            ->selectRaw("NULLIF(analytics_events.referrer_host, '') as raw_host");

        $sourceRows = DB::query()
            ->fromSub($attributedSessions, 'attributed_sessions')
            ->select(['source', 'medium', 'raw_host'])
            ->selectRaw('COUNT(*) as sessions')
            ->groupBy(['source', 'medium', 'raw_host'])
            ->get();
        $sources = app(TrafficSourceNormalizer::class)->aggregate($sourceRows);
        $totalSessions = (int) $sources->sum('sessions');

        return $sources
            ->map(function ($row) use ($totalSessions) {
                $row->percentage = $totalSessions > 0
                    ? round(((int) $row->sessions / $totalSessions) * 100, 1)
                    : 0.0;

                return $row;
            })
            ->take(10)
            ->values();
    }

    /**
     * @return array{source: string, medium: string, bindings: array<int, string>}
     */
    private function attributionSqlExpressions(): array
    {
        $originExpression = "LOWER(COALESCE(NULLIF(analytics_events.acquisition_source, ''), NULLIF(analytics_events.referrer_host, '')))";
        $conditions = [];
        $bindings = [];
        foreach ((array) config('analytics.internal_referrer_hosts', []) as $host) {
            $host = strtolower(trim((string) $host, ". \t\n\r\0\x0B"));
            if ($host === '') {
                continue;
            }

            $conditions[] = '('.$originExpression.' = ? OR '.$originExpression.' LIKE ?)';
            $bindings[] = $host;
            $bindings[] = '%.'.$host;
        }

        $internalCondition = $conditions !== [] ? implode(' OR ', $conditions) : '0 = 1';
        $internalCondition = '('.$internalCondition.') OR '.$originExpression." = 'stemmechanics' OR ".$originExpression." LIKE 'stemmechanics.%' OR ".$originExpression." LIKE '%.stemmechanics.%'";
        $externalSource = "CASE WHEN ({$internalCondition}) THEN NULL ELSE COALESCE(NULLIF(analytics_events.acquisition_source, ''), NULLIF(analytics_events.referrer_host, '')) END";

        return [
            'source' => "COALESCE(NULLIF(analytics_events.utm_source, ''), {$externalSource}, 'Direct / unknown')",
            'medium' => "COALESCE(NULLIF(analytics_events.utm_medium, ''), CASE WHEN {$externalSource} IS NULL OR analytics_events.acquisition_source = 'Direct / unknown' THEN 'direct' ELSE 'referral' END)",
            'bindings' => $bindings,
        ];
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     */
    private function workshopsChart(array $buckets): array
    {
        $views = $this->bucketedValues(
            AnalyticsEvent::query()->whereIn('route_name', ['workshop.index', 'workshop.show']),
            'analytics_events.created_at',
            $buckets
        );

        return $this->chart('Workshops', 'Workshop Activity', 'Workshop page views throughout the selected period.', $buckets, [
            ['label' => 'Workshop views', 'color' => 'sky', 'values' => $views],
        ]);
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     */
    private function ticketsChart(array $buckets): array
    {
        $tickets = $this->bucketedValues(
            Ticket::query()
                ->join('workshops', 'workshops.id', '=', 'tickets.workshop_id')
                ->whereIn('workshops.registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
                ->whereIn('tickets.status', Ticket::activePurchasedStatuses()),
            'tickets.created_at',
            $buckets
        );
        $clicks = $this->bucketedValues(
            AnalyticsEvent::query()
                ->join('workshops', 'workshops.id', '=', 'analytics_events.workshop_id')
                ->where('workshops.registration', 'link')
                ->where('analytics_events.event_type', AnalyticsEvent::TYPE_REGISTRATION_CLICK),
            'analytics_events.created_at',
            $buckets,
            'analytics_events.session_token'
        );

        return $this->chart('Tickets', 'Ticket Activity', 'Internal ticket sales and unique visits to external ticketing systems.', $buckets, [
            ['label' => 'Tickets sold', 'color' => 'emerald', 'values' => $tickets],
            ['label' => 'External clicks', 'color' => 'violet', 'values' => $clicks],
        ]);
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     */
    private function storeChart(array $buckets): array
    {
        $storeViews = $this->bucketedValues(
            AnalyticsEvent::query()->where('route_name', 'shop.index'),
            'analytics_events.created_at',
            $buckets
        );
        $productViews = $this->bucketedValues(
            AnalyticsEvent::query()->where('route_name', 'shop.product.show'),
            'analytics_events.created_at',
            $buckets
        );
        $quantitySql = $this->storeItemSoldQuantitySql();
        $itemsSold = $this->bucketedValues(
            StoreOrderItem::query()
                ->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
                ->whereNotNull('store_orders.paid_at')
                ->where('store_orders.status', '!=', StoreOrder::STATUS_CANCELLED),
            'store_orders.paid_at',
            $buckets,
            null,
            'COALESCE(SUM('.$quantitySql.'), 0)'
        );

        return $this->chart('Store', 'Store Activity', 'Store visits, product views and items sold.', $buckets, [
            ['label' => 'Store views', 'color' => 'sky', 'values' => $storeViews],
            ['label' => 'Product views', 'color' => 'violet', 'values' => $productViews],
            ['label' => 'Items sold', 'color' => 'emerald', 'values' => $itemsSold],
        ]);
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     */
    private function growthChart(array $buckets): array
    {
        $users = $this->cumulativeBucketedValues(
            User::query(),
            'users.email_verified_at',
            $buckets
        );
        $subscriptions = $this->cumulativeBucketedValues(
            EmailSubscriptions::query(),
            'email_subscriptions.confirmed',
            $buckets
        );

        return $this->chart('Growth', 'Audience Growth', 'Cumulative verified users and confirmed email subscriptions.', $buckets, [
            ['label' => 'Total users', 'color' => 'sky', 'values' => $users],
            ['label' => 'Total subscriptions', 'color' => 'violet', 'values' => $subscriptions],
        ]);
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     * @return array<int, float|int>
     */
    private function cumulativeBucketedValues($query, string $column, array $buckets): array
    {
        $baseline = (clone $query)
            ->whereNotNull($column)
            ->where($column, '<', $buckets[0]['start'])
            ->count();
        $additions = $this->bucketedValues(
            (clone $query)->whereNotNull($column),
            $column,
            $buckets
        );

        $total = $baseline;

        return array_map(function (float|int $addition) use (&$total): float|int {
            $total += $addition;

            return $total;
        }, $additions);
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     * @param  array<int, array{label: string, color: string, type?: string, values: array<int, float|int>}>  $series
     */
    private function chart(string $card, string $title, string $description, array $buckets, array $series, string $valuePrefix = ''): array
    {
        return [
            'card' => $card,
            'title' => $title,
            'description' => $description,
            'valuePrefix' => $valuePrefix,
            'labels' => array_column($buckets, 'label'),
            'series' => $series,
        ];
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     * @return array<int, float|int>
     */
    private function bucketedValues($query, string $column, array $buckets, ?string $distinct = null, string $aggregate = ''): array
    {
        $bucketSql = $this->bucketCaseSql($column, $buckets);
        $aggregate = $aggregate !== '' ? $aggregate : ($distinct ? 'COUNT(DISTINCT '.$distinct.')' : 'COUNT(*)');
        $rows = $query
            ->where($column, '>=', $buckets[0]['start'])
            ->where($column, '<', $buckets[array_key_last($buckets)]['end'])
            ->selectRaw($bucketSql['sql'].' as bucket_index', $bucketSql['bindings'])
            ->selectRaw($aggregate.' as aggregate_value')
            ->groupBy('bucket_index')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->bucket_index);

        return array_map(
            fn (int $index): float|int => (float) ($rows->get($index)?->aggregate_value ?? 0),
            array_keys($buckets)
        );
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     */
    private function websiteTrafficChart(array $buckets): array
    {
        $bucketSql = $this->bucketCaseSql('created_at', $buckets);
        $rows = AnalyticsEvent::query()
            ->where('event_type', '!=', AnalyticsEvent::TYPE_REGISTRATION_CLICK)
            ->where('created_at', '>=', $buckets[0]['start'])
            ->where('created_at', '<', $buckets[array_key_last($buckets)]['end'])
            ->selectRaw($bucketSql['sql'].' as bucket_index', $bucketSql['bindings'])
            ->selectRaw('COUNT(*) as views, COUNT(DISTINCT visitor_hash) as visitors')
            ->groupBy('bucket_index')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->bucket_index);

        return [
            'card' => 'Website',
            'title' => 'Website Traffic',
            'description' => 'Page views and unique visitors throughout the selected period.',
            'valuePrefix' => '',
            'labels' => array_column($buckets, 'label'),
            'series' => [
                ['label' => 'Page views', 'color' => 'sky', 'values' => array_map(fn (int $index): int => (int) ($rows->get($index)?->views ?? 0), array_keys($buckets))],
                ['label' => 'Unique visitors', 'color' => 'violet', 'values' => array_map(fn (int $index): int => (int) ($rows->get($index)?->visitors ?? 0), array_keys($buckets))],
            ],
        ];
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     */
    private function financeChart(array $buckets): array
    {
        $paymentBucketSql = $this->bucketCaseSql('received_on', $buckets);
        $payments = Payment::query()
            ->whereNotNull('received_on')
            ->where('received_on', '>=', $buckets[0]['start'])
            ->where('received_on', '<', $buckets[array_key_last($buckets)]['end'])
            ->selectRaw($paymentBucketSql['sql'].' as bucket_index', $paymentBucketSql['bindings'])
            ->selectRaw('SUM(CASE WHEN kind = ? THEN total_amount ELSE 0 END) as income, SUM(CASE WHEN kind = ? THEN total_amount ELSE 0 END) as refunds', [Payment::KIND_PAYMENT, Payment::KIND_REFUND])
            ->groupBy('bucket_index')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->bucket_index);

        $expenseBucketSql = $this->bucketCaseSql('paid_on', $buckets);
        $expenses = Expense::query()
            ->whereNotNull('paid_on')
            ->where('paid_on', '>=', $buckets[0]['start']->toDateString())
            ->where('paid_on', '<', $buckets[array_key_last($buckets)]['end']->toDateString())
            ->selectRaw($expenseBucketSql['sql'].' as bucket_index', $expenseBucketSql['bindings'])
            ->selectRaw('SUM(total_amount) as expenses')
            ->groupBy('bucket_index')
            ->get()
            ->keyBy(fn ($row): int => (int) $row->bucket_index);

        $netIncome = [];
        $expenseValues = [];
        $profit = [];
        foreach (array_keys($buckets) as $index) {
            $bucketIncome = round((float) ($payments->get($index)?->income ?? 0), 2);
            $bucketRefunds = round((float) ($payments->get($index)?->refunds ?? 0), 2);
            $bucketExpenses = round((float) ($expenses->get($index)?->expenses ?? 0), 2);
            $netIncome[] = round($bucketIncome - $bucketRefunds, 2);
            $expenseValues[] = $bucketExpenses;
            $profit[] = round($bucketIncome - $bucketRefunds - $bucketExpenses, 2);
        }

        return [
            'card' => 'Finance',
            'title' => 'Financial Performance',
            'description' => 'Income after refunds and expenses, with the resulting profit shown as a line.',
            'valuePrefix' => '$',
            'labels' => array_column($buckets, 'label'),
            'series' => [
                ['label' => 'Net income', 'color' => 'sky', 'type' => 'bar', 'values' => $netIncome],
                ['label' => 'Expenses', 'color' => 'amber', 'type' => 'bar', 'values' => $expenseValues],
                ['label' => 'Profit', 'color' => 'emerald', 'type' => 'line', 'values' => $profit],
            ],
        ];
    }

    /**
     * @return array<int, array{label: string, start: Carbon, end: Carbon}>
     */
    private function chartBuckets(string $period, Carbon $start, Carbon $end): array
    {
        $buckets = [];
        $cursor = (clone $start);
        $endExclusive = (clone $end)->addMicrosecond();

        while ($cursor->lt($endExclusive)) {
            $bucketStart = clone $cursor;
            [$next, $label] = match ($period) {
                'day' => [(clone $cursor)->addHours(4), $cursor->format('ga')],
                'year', 'overview' => [(clone $cursor)->addMonth(), $cursor->format('M Y')],
                'quarter' => [(clone $cursor)->addWeek(), $cursor->format('j M')],
                default => [(clone $cursor)->addDay(), $cursor->format('D j')],
            };
            $bucketEnd = $next->min($endExclusive);
            $buckets[] = ['label' => $label, 'start' => $bucketStart, 'end' => $bucketEnd];
            $cursor = $next;
        }

        return $buckets;
    }

    /**
     * @param  array<int, array{label: string, start: Carbon, end: Carbon}>  $buckets
     * @return array{sql: string, bindings: array<int, Carbon|string>}
     */
    private function bucketCaseSql(string $column, array $buckets): array
    {
        $parts = [];
        $bindings = [];
        foreach ($buckets as $index => $bucket) {
            $parts[] = "WHEN {$column} >= ? AND {$column} < ? THEN {$index}";
            $bindings[] = $column === 'paid_on' ? $bucket['start']->toDateString() : $bucket['start'];
            $bindings[] = $column === 'paid_on' ? $bucket['end']->toDateString() : $bucket['end'];
        }

        return ['sql' => '(CASE '.implode(' ', $parts).' END)', 'bindings' => $bindings];
    }

    private function metric(string $label, float|int $current, float|int $previous, int $decimals = 0): array
    {
        return [
            'label' => $label,
            'current' => $this->formatNumber($current, $decimals),
            'previous' => $this->formatNumber($previous, $decimals),
            'change' => $this->formatChange($current, $previous, false, $decimals),
            'tone' => $this->changeTone($current, $previous),
        ];
    }

    private function moneyMetric(string $label, float|int $current, float|int $previous, bool $higherIsBetter = true): array
    {
        return [
            'label' => $label,
            'current' => $this->formatMoney($current),
            'previous' => $this->formatMoney($previous),
            'change' => $this->formatChange($current, $previous, true),
            'tone' => $this->changeTone($current, $previous, $higherIsBetter),
        ];
    }

    private function changeTone(float|int $current, float|int $previous, bool $higherIsBetter = true): string
    {
        if ($higherIsBetter) {
            return (float) $current >= (float) $previous ? 'emerald' : 'rose';
        }

        return (float) $current <= (float) $previous ? 'emerald' : 'rose';
    }

    private function formatChange(float|int $current, float|int $previous, bool $money = false, int $decimals = 0): string
    {
        $current = (float) $current;
        $previous = (float) $previous;
        $difference = round($current - $previous, $money ? 2 : $decimals);

        if (abs($difference) < 0.0001) {
            return 'No change vs previous period';
        }

        $prefix = $difference > 0 ? '+' : '-';
        $amount = abs($difference);
        $amountLabel = $money
            ? '$'.number_format($amount, 2)
            : number_format($amount, $decimals);

        if ($previous <= 0.0001) {
            return $prefix.$amountLabel;
        }

        $percent = round(($difference / $previous) * 100, 1);
        $percentLabel = ($percent > 0 ? '+' : '').number_format($percent, 1).'%';

        return $prefix.$amountLabel.' ('.$percentLabel.')';
    }

    private function formatNumber(float|int $value, int $decimals = 0): string
    {
        return number_format((float) $value, $decimals);
    }

    private function formatMoney(float|int $value): string
    {
        return '$'.number_format((float) $value, 2);
    }

    /**
     * @return array{start: Carbon, end: Carbon}
     */
    private function periodWindow(string $period, Carbon $reference): array
    {
        $period = array_key_exists($period, self::PERIODS) ? $period : 'overview';

        return match ($period) {
            'overview' => [
                'start' => (clone $reference)->startOfMonth()->subMonths(11),
                'end' => (clone $reference)->endOfDay(),
            ],
            'day' => [
                'start' => (clone $reference)->startOfDay(),
                'end' => (clone $reference)->endOfDay(),
            ],
            'month' => [
                'start' => (clone $reference)->startOfMonth(),
                'end' => (clone $reference)->endOfMonth(),
            ],
            'quarter' => [
                'start' => (clone $reference)->startOfQuarter(),
                'end' => (clone $reference)->endOfQuarter(),
            ],
            'year' => [
                'start' => (clone $reference)->startOfYear(),
                'end' => (clone $reference)->endOfYear(),
            ],
            default => [
                'start' => (clone $reference)->startOfWeek(Carbon::SUNDAY),
                'end' => (clone $reference)->endOfWeek(Carbon::SATURDAY),
            ],
        };
    }

    private function countWorkshopTicketSalesBetween(Carbon $start, Carbon $end): int
    {
        return Ticket::query()
            ->join('workshops', 'workshops.id', '=', 'tickets.workshop_id')
            ->whereIn('workshops.registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
            ->where('tickets.created_at', '>=', $start)
            ->where('tickets.created_at', '<', $end)
            ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
            ->count();
    }

    private function countExternalRegistrationClickersBetween(Carbon $start, Carbon $end): int
    {
        return AnalyticsEvent::query()
            ->join('workshops', 'workshops.id', '=', 'analytics_events.workshop_id')
            ->where('workshops.registration', 'link')
            ->where('analytics_events.event_type', AnalyticsEvent::TYPE_REGISTRATION_CLICK)
            ->where('analytics_events.created_at', '>=', $start)
            ->where('analytics_events.created_at', '<', $end)
            ->distinct('analytics_events.session_token')
            ->count('analytics_events.session_token');
    }

    private function sumPaymentsBetween(Carbon $start, Carbon $end, string $kind): float
    {
        return round((float) Payment::query()
            ->where('kind', $kind)
            ->whereNotNull('received_on')
            ->where('received_on', '>=', $start)
            ->where('received_on', '<', $end)
            ->sum('total_amount'), 2);
    }

    private function sumExpensesBetween(Carbon $start, Carbon $end): float
    {
        return round((float) Expense::query()
            ->whereNotNull('paid_on')
            ->whereDate('paid_on', '>=', $start->toDateString())
            ->whereDate('paid_on', '<=', $end->toDateString())
            ->sum('total_amount'), 2);
    }

    private function countAnalyticsEventsBetween(Carbon $start, Carbon $end): int
    {
        return AnalyticsEvent::query()
            ->where('event_type', '!=', AnalyticsEvent::TYPE_REGISTRATION_CLICK)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    private function countAnalyticsVisitorsBetween(Carbon $start, Carbon $end): int
    {
        return AnalyticsEvent::query()
            ->where('event_type', '!=', AnalyticsEvent::TYPE_REGISTRATION_CLICK)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereNotNull('visitor_hash')
            ->distinct('visitor_hash')
            ->count('visitor_hash');
    }

    private function countUsersAt(Carbon $end): int
    {
        return User::query()
            ->whereNotNull('email_verified_at')
            ->where('email_verified_at', '<', $end)
            ->count();
    }

    private function countSubscriptionsAt(Carbon $end): int
    {
        return EmailSubscriptions::query()
            ->whereNotNull('confirmed')
            ->where('confirmed', '<', $end)
            ->count();
    }

    private function countStoreItemsSoldBetween(Carbon $start, Carbon $end): int
    {
        $quantitySql = $this->storeItemSoldQuantitySql();

        $itemsSold = StoreOrderItem::query()
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
            ->whereNotNull('store_orders.paid_at')
            ->where('store_orders.paid_at', '>=', $start)
            ->where('store_orders.paid_at', '<', $end)
            ->where('store_orders.status', '!=', StoreOrder::STATUS_CANCELLED)
            ->selectRaw('COALESCE(SUM('.$quantitySql.'), 0) as items_sold')
            ->value('items_sold');

        return max(0, (int) ($itemsSold ?? 0));
    }

    private function countAnalyticsEventsForRoutesBetween(Carbon $start, Carbon $end, array $routeNames): int
    {
        if ($routeNames === []) {
            return 0;
        }

        return AnalyticsEvent::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereIn('route_name', $routeNames)
            ->count();
    }

    private function topWorkshopSalesRows(Carbon $start, Carbon $end): Collection
    {
        $ticketCounts = DB::table('tickets')
            ->join('workshops', 'workshops.id', '=', 'tickets.workshop_id')
            ->whereIn('workshops.registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
            ->where('tickets.created_at', '>=', $start)
            ->where('tickets.created_at', '<', $end)
            ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
            ->selectRaw('tickets.workshop_id, COUNT(*) as tickets_sold')
            ->groupBy('tickets.workshop_id');

        $viewCounts = DB::table('analytics_events')
            ->where('event_type', AnalyticsEvent::TYPE_PAGE_VIEW)
            ->where('route_name', 'workshop.show')
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('workshop_id, COUNT(*) as views')
            ->groupBy('workshop_id');

        $externalClickCounts = DB::table('analytics_events')
            ->where('event_type', AnalyticsEvent::TYPE_REGISTRATION_CLICK)
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->selectRaw('workshop_id, COUNT(DISTINCT session_token) as unique_clicks')
            ->groupBy('workshop_id');

        return DB::table('workshops')
            ->leftJoin('locations', 'locations.id', '=', 'workshops.location_id')
            ->leftJoinSub($viewCounts, 'view_counts', function ($join): void {
                $join->on('view_counts.workshop_id', '=', 'workshops.id');
            })
            ->leftJoinSub($ticketCounts, 'ticket_counts', function ($join): void {
                $join->on('ticket_counts.workshop_id', '=', 'workshops.id');
            })
            ->leftJoinSub($externalClickCounts, 'external_click_counts', function ($join): void {
                $join->on('external_click_counts.workshop_id', '=', 'workshops.id');
            })
            ->where(function ($query): void {
                $query->whereNotNull('view_counts.views')
                    ->orWhereNotNull('ticket_counts.tickets_sold')
                    ->orWhereNotNull('external_click_counts.unique_clicks');
            })
            ->selectRaw('
                workshops.id as workshop_id,
                workshops.title as workshop_title,
                workshops.starts_at as workshop_starts_at,
                workshops.registration as registration_type,
                COALESCE(locations.name, \'\') as location_name,
                COALESCE(view_counts.views, 0) as views,
                COALESCE(ticket_counts.tickets_sold, 0) as tickets_sold,
                COALESCE(external_click_counts.unique_clicks, 0) as unique_clicks
            ')
            ->orderByDesc('views')
            ->orderByDesc('tickets_sold')
            ->orderByDesc('unique_clicks')
            ->orderBy('workshops.starts_at')
            ->limit(10)
            ->get()
            ->map(
                /**
                 * @return array{
                 *     workshop_id: non-empty-string,
                 *     workshop_title: non-empty-string,
                 *     workshop_starts_at: string|null,
                 *     location_name: string,
                 *     views: int,
                 *     registration_count: int|null,
                 *     registration_label: string|null,
                 * }
                 */
                function ($row): array {
                    $workshopId = (string) ($row->workshop_id ?? '');
                    $workshopTitle = (string) ($row->workshop_title ?? '');
                    $workshopStartsAt = (string) ($row->workshop_starts_at ?? '');
                    $registrationType = (string) ($row->registration_type ?? '');

                    if ($workshopId === '' || $workshopTitle === '') {
                        throw new \RuntimeException('Workshop sales row is missing required fields.');
                    }

                    return [
                        'workshop_id' => $workshopId,
                        'workshop_title' => $workshopTitle,
                        'workshop_starts_at' => $workshopStartsAt !== '' ? $workshopStartsAt : null,
                        'location_name' => (string) ($row->location_name ?? ''),
                        'views' => (int) $row->views,
                        'registration_count' => match ($registrationType) {
                            'tickets' => (int) $row->tickets_sold,
                            'link' => (int) $row->unique_clicks,
                            default => null,
                        },
                        'registration_label' => match ($registrationType) {
                            'tickets' => 'tickets sold',
                            'link' => 'unique clicks',
                            default => null,
                        },
                    ];
                })
            ->values();
    }

    /**
     * @return Collection<int, array{
     *     product_id: string,
     *     product_title: string,
     *     views: int,
     *     items_sold: int
     * }>
     */
    private function topStoreSalesRows(Carbon $start, Carbon $end): Collection
    {
        $quantitySql = $this->storeItemSoldQuantitySql();

        $productViews = DB::table('analytics_events')
            ->where('analytics_events.route_name', 'shop.product.show')
            ->where('analytics_events.created_at', '>=', $start)
            ->where('analytics_events.created_at', '<', $end)
            ->where('analytics_events.path', 'like', '/store/%')
            ->selectRaw('SUBSTR(analytics_events.path, 8) as product_slug, COUNT(*) as views')
            ->groupBy(DB::raw('SUBSTR(analytics_events.path, 8)'));

        return DB::table('store_order_items')
            ->join('store_orders', 'store_orders.id', '=', 'store_order_items.store_order_id')
            ->leftJoinSub($productViews, 'product_views', function ($join): void {
                $join->on('product_views.product_slug', '=', 'store_order_items.product_slug');
            })
            ->whereNotNull('store_orders.paid_at')
            ->where('store_orders.paid_at', '>=', $start)
            ->where('store_orders.paid_at', '<', $end)
            ->where('store_orders.status', '!=', StoreOrder::STATUS_CANCELLED)
            ->selectRaw('
                store_order_items.product_id as product_id,
                COALESCE(store_order_items.product_title, \'\') as product_title,
                COALESCE(product_views.views, 0) as views,
                SUM('.$quantitySql.') as items_sold
            ')
            ->groupBy('store_order_items.product_id', 'store_order_items.product_title', 'product_views.views')
            ->havingRaw('SUM('.$quantitySql.') > 0')
            ->orderByDesc('items_sold')
            ->orderByDesc('views')
            ->orderBy('store_order_items.product_title')
            ->limit(10)
            ->get()
            ->map(
                /**
                 * @return array{
                 *     product_id: string,
                 *     product_title: string,
                 *     views: int,
                 *     items_sold: int
                 * }
                 */
                function ($row): array {
                    $productId = (string) ($row->product_id ?? '');
                    $productTitle = (string) ($row->product_title ?? '');

                    return [
                        'product_id' => $this->assertNonEmptyString($productId, 'Store sales row is missing required fields.'),
                        'product_title' => $this->assertNonEmptyString($productTitle, 'Store sales row is missing required fields.'),
                        'views' => (int) $row->views,
                        'items_sold' => (int) $row->items_sold,
                    ];
                }
            )
            ->values();
    }

    /**
     * @return non-empty-string
     */
    private function assertNonEmptyString(string $value, string $message): string
    {
        if ($value === '') {
            throw new \RuntimeException($message);
        }

        return $value;
    }

    private function storeItemSoldQuantitySql(string $tableAlias = 'store_order_items'): string
    {
        return 'CASE WHEN COALESCE('.$tableAlias.'.quantity, 0) - COALESCE('.$tableAlias.'.cancelled_available_quantity, 0) - COALESCE('.$tableAlias.'.cancelled_delayed_quantity, 0) > 0 THEN COALESCE('.$tableAlias.'.quantity, 0) - COALESCE('.$tableAlias.'.cancelled_available_quantity, 0) - COALESCE('.$tableAlias.'.cancelled_delayed_quantity, 0) ELSE 0 END';
    }
}
