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
        'day' => ['label' => 'This day'],
        'week' => ['label' => 'This week'],
        'month' => ['label' => 'This month'],
        'quarter' => ['label' => 'This quarter'],
        'year' => ['label' => 'This year'],
    ];

    private const INTERNAL_WORKSHOP_REGISTRATIONS = ['tickets'];

    public function build(string $period = 'week'): array
    {
        $periodKey = array_key_exists($period, self::PERIODS) ? $period : 'week';
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

        $newUsersCurrent = $this->countUsersBetween($currentStart, $currentEnd);
        $newUsersPrevious = $this->countUsersBetween($previousStart, $previousEnd);
        $newSubscriptionsCurrent = $this->countSubscriptionsBetween($currentStart, $currentEnd);
        $newSubscriptionsPrevious = $this->countSubscriptionsBetween($previousStart, $previousEnd);

        $workshopSalesRows = $this->topWorkshopSalesRows($currentStart, $currentEnd);
        $storeSalesRows = $this->topStoreSalesRows($currentStart, $currentEnd);

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
                    'description' => 'Workshop ticket sales in the selected period.',
                    'links' => [
                        ['label' => 'Tickets', 'route' => route('admin.ticket.index'), 'icon' => 'fa-solid fa-ticket'],
                    ],
                    'metrics' => [
                        $this->metric('Tickets sold', $ticketsSoldCurrent, $ticketsSoldPrevious),
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
                    'description' => 'New users and subscriptions recorded in the selected period.',
                    'links' => [
                        ['label' => 'Users', 'route' => route('admin.user.index'), 'icon' => 'fa-solid fa-users'],
                        ['label' => 'Subscriptions', 'route' => route('admin.subscription.index'), 'icon' => 'fa-solid fa-envelope-open-text'],
                    ],
                    'metrics' => [
                        $this->metric('New users', $newUsersCurrent, $newUsersPrevious),
                        $this->metric('New subscriptions', $newSubscriptionsCurrent, $newSubscriptionsPrevious),
                    ],
                ],
            ],
            'workshopSalesRows' => $workshopSalesRows,
            'storeSalesRows' => $storeSalesRows,
        ];
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
        $period = array_key_exists($period, self::PERIODS) ? $period : 'week';

        return match ($period) {
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
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    private function countAnalyticsVisitorsBetween(Carbon $start, Carbon $end): int
    {
        return AnalyticsEvent::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->whereNotNull('visitor_hash')
            ->distinct('visitor_hash')
            ->count('visitor_hash');
    }

    private function countUsersBetween(Carbon $start, Carbon $end): int
    {
        return User::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    private function countSubscriptionsBetween(Carbon $start, Carbon $end): int
    {
        return EmailSubscriptions::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
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

        return DB::table('analytics_events')
            ->join('workshops', 'workshops.id', '=', 'analytics_events.workshop_id')
            ->leftJoin('locations', 'locations.id', '=', 'workshops.location_id')
            ->leftJoinSub($ticketCounts, 'ticket_counts', function ($join): void {
                $join->on('ticket_counts.workshop_id', '=', 'workshops.id');
            })
            ->where('analytics_events.route_name', 'workshop.show')
            ->where('analytics_events.created_at', '>=', $start)
            ->where('analytics_events.created_at', '<', $end)
            ->selectRaw('
                workshops.id as workshop_id,
                workshops.title as workshop_title,
                workshops.starts_at as workshop_starts_at,
                COALESCE(locations.name, \'\') as location_name,
                COUNT(*) as views,
                COALESCE(ticket_counts.tickets_sold, 0) as tickets_sold
            ')
            ->groupBy(
                'workshops.id',
                'workshops.title',
                'workshops.starts_at',
                'locations.name',
                'ticket_counts.tickets_sold'
            )
            ->orderByDesc('views')
            ->orderByDesc('tickets_sold')
            ->orderBy('workshops.starts_at')
            ->limit(5)
            ->get()
            ->map(
                /**
                 * @return array{
                 *     workshop_id: non-empty-string,
                 *     workshop_title: non-empty-string,
                 *     workshop_starts_at: string|null,
                 *     location_name: string,
                 *     views: int,
                 *     tickets_sold: int,
                 * }
                 */
                function ($row): array {
                    $workshopId = (string) ($row->workshop_id ?? '');
                    $workshopTitle = (string) ($row->workshop_title ?? '');
                    $workshopStartsAt = (string) ($row->workshop_starts_at ?? '');

                    if ($workshopId === '' || $workshopTitle === '') {
                        throw new \RuntimeException('Workshop sales row is missing required fields.');
                    }

                    return [
                        'workshop_id' => $workshopId,
                        'workshop_title' => $workshopTitle,
                        'workshop_starts_at' => $workshopStartsAt !== '' ? $workshopStartsAt : null,
                        'location_name' => (string) ($row->location_name ?? ''),
                        'views' => (int) $row->views,
                        'tickets_sold' => (int) $row->tickets_sold,
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
            ->selectRaw("SUBSTR(analytics_events.path, 8) as product_slug, COUNT(*) as views")
            ->groupBy(DB::raw("SUBSTR(analytics_events.path, 8)"));

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
            ->limit(5)
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
