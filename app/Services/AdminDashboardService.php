<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\EmailSubscriptions;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AdminDashboardService
{
    private const PERIODS = [
        'day' => ['label' => 'This day'],
        'week' => ['label' => 'This week'],
        'month' => ['label' => 'This month'],
        'quarter' => ['label' => 'This quarter'],
        'year' => ['label' => 'This year'],
    ];

    private const INTERNAL_WORKSHOP_REGISTRATIONS = ['tickets', 'classroom'];

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

        $workshopsCreatedCurrent = $this->countWorkshopsCreatedBetween($currentStart, $currentEnd);
        $workshopsCreatedPrevious = $this->countWorkshopsCreatedBetween($previousStart, $previousEnd);
        $workshopsStartingCurrent = $this->countWorkshopsStartingBetween($currentStart, $currentEnd);
        $workshopsStartingPrevious = $this->countWorkshopsStartingBetween($previousStart, $previousEnd);

        $ticketsSoldCurrent = $this->countWorkshopTicketSalesBetween($currentStart, $currentEnd);
        $ticketsSoldPrevious = $this->countWorkshopTicketSalesBetween($previousStart, $previousEnd);
        $earlyBirdTicketsCurrent = $this->countWorkshopEarlyBirdSalesBetween($currentStart, $currentEnd);
        $earlyBirdTicketsPrevious = $this->countWorkshopEarlyBirdSalesBetween($previousStart, $previousEnd);
        $ticketedWorkshopsCurrent = $this->countTicketedWorkshopsBetween($currentStart, $currentEnd);
        $ticketedWorkshopsPrevious = $this->countTicketedWorkshopsBetween($previousStart, $previousEnd);

        $avgTicketsPerWorkshopCurrent = $ticketedWorkshopsCurrent > 0 ? round($ticketsSoldCurrent / $ticketedWorkshopsCurrent, 1) : 0.0;
        $avgTicketsPerWorkshopPrevious = $ticketedWorkshopsPrevious > 0 ? round($ticketsSoldPrevious / $ticketedWorkshopsPrevious, 1) : 0.0;

        $incomeGrossCurrent = $this->sumPaymentsBetween($currentStart, $currentEnd, Payment::KIND_PAYMENT);
        $incomeGrossPrevious = $this->sumPaymentsBetween($previousStart, $previousEnd, Payment::KIND_PAYMENT);
        $refundsCurrent = $this->sumPaymentsBetween($currentStart, $currentEnd, Payment::KIND_REFUND);
        $refundsPrevious = $this->sumPaymentsBetween($previousStart, $previousEnd, Payment::KIND_REFUND);
        $netIncomeCurrent = round($incomeGrossCurrent - $refundsCurrent, 2);
        $netIncomePrevious = round($incomeGrossPrevious - $refundsPrevious, 2);
        $expensesCurrent = $this->sumExpensesBetween($currentStart, $currentEnd);
        $expensesPrevious = $this->sumExpensesBetween($previousStart, $previousEnd);
        $netAfterExpensesCurrent = round($netIncomeCurrent - $expensesCurrent, 2);
        $netAfterExpensesPrevious = round($netIncomePrevious - $expensesPrevious, 2);

        $analyticsViewsCurrent = $this->countAnalyticsEventsBetween($currentStart, $currentEnd);
        $analyticsViewsPrevious = $this->countAnalyticsEventsBetween($previousStart, $previousEnd);
        $analyticsSessionsCurrent = $this->countAnalyticsSessionsBetween($currentStart, $currentEnd);
        $analyticsSessionsPrevious = $this->countAnalyticsSessionsBetween($previousStart, $previousEnd);
        $analyticsVisitorsCurrent = $this->countAnalyticsVisitorsBetween($currentStart, $currentEnd);
        $analyticsVisitorsPrevious = $this->countAnalyticsVisitorsBetween($previousStart, $previousEnd);

        $newUsersCurrent = $this->countUsersBetween($currentStart, $currentEnd);
        $newUsersPrevious = $this->countUsersBetween($previousStart, $previousEnd);
        $newSubscriptionsCurrent = $this->countSubscriptionsBetween($currentStart, $currentEnd);
        $newSubscriptionsPrevious = $this->countSubscriptionsBetween($previousStart, $previousEnd);

        $workshopSalesRows = $this->topWorkshopSalesRows($currentStart, $currentEnd);

        return [
            'period' => $periodKey,
            'periodLabel' => $periodConfig['label'],
            'periodStart' => $currentStart,
            'periodEnd' => $currentEnd,
            'cards' => [
                [
                    'title' => 'Workshops',
                    'description' => 'Workshop creation and ticketed activity in the selected period.',
                    'links' => [
                        ['label' => 'Workshops', 'route' => route('admin.workshop.index'), 'icon' => 'fa-solid fa-bullhorn'],
                        ['label' => 'Tickets', 'route' => route('admin.ticket.index'), 'icon' => 'fa-solid fa-ticket'],
                    ],
                    'metrics' => [
                        $this->metric('Created', $workshopsCreatedCurrent, $workshopsCreatedPrevious),
                        $this->metric('Starting in period', $workshopsStartingCurrent, $workshopsStartingPrevious),
                        $this->metric('Ticketed workshops with sales', $ticketedWorkshopsCurrent, $ticketedWorkshopsPrevious),
                        $this->metric('Avg tickets per ticketed workshop', $avgTicketsPerWorkshopCurrent, $avgTicketsPerWorkshopPrevious, 1),
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
                        $this->metric('Early-bird tickets sold', $earlyBirdTicketsCurrent, $earlyBirdTicketsPrevious),
                    ],
                ],
                [
                    'title' => 'Finance',
                    'description' => 'Income, refunds and expenses during the selected period.',
                    'links' => [
                        ['label' => 'BAS', 'route' => route('admin.bas.index'), 'icon' => 'fa-solid fa-calculator'],
                        ['label' => 'Expenses', 'route' => route('admin.expense.index'), 'icon' => 'fa-solid fa-receipt'],
                        ['label' => 'Invoices', 'route' => route('admin.invoice.index'), 'icon' => 'fa-solid fa-file-invoice-dollar'],
                    ],
                    'metrics' => [
                        $this->moneyMetric('Gross income', $incomeGrossCurrent, $incomeGrossPrevious),
                        $this->moneyMetric('Refunds', $refundsCurrent, $refundsPrevious, false),
                        $this->moneyMetric('Net income', $netIncomeCurrent, $netIncomePrevious),
                        $this->moneyMetric('Expenses', $expensesCurrent, $expensesPrevious, false),
                        $this->moneyMetric('Net after expenses', $netAfterExpensesCurrent, $netAfterExpensesPrevious),
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
                        $this->metric('Sessions', $analyticsSessionsCurrent, $analyticsSessionsPrevious),
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

    private function countWorkshopsCreatedBetween(Carbon $start, Carbon $end): int
    {
        return Workshop::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->count();
    }

    private function countWorkshopsStartingBetween(Carbon $start, Carbon $end): int
    {
        return Workshop::query()
            ->whereIn('registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
            ->whereNotNull('starts_at')
            ->where('starts_at', '>=', $start)
            ->where('starts_at', '<', $end)
            ->count();
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

    private function countWorkshopEarlyBirdSalesBetween(Carbon $start, Carbon $end): int
    {
        return Ticket::query()
            ->join('workshops', 'workshops.id', '=', 'tickets.workshop_id')
            ->whereIn('workshops.registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
            ->where('tickets.created_at', '>=', $start)
            ->where('tickets.created_at', '<', $end)
            ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
            ->where('tickets.is_early_bird', true)
            ->count();
    }

    private function countTicketedWorkshopsBetween(Carbon $start, Carbon $end): int
    {
        return Ticket::query()
            ->join('workshops', 'workshops.id', '=', 'tickets.workshop_id')
            ->whereIn('workshops.registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
            ->where('tickets.created_at', '>=', $start)
            ->where('tickets.created_at', '<', $end)
            ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
            ->distinct()
            ->count('tickets.workshop_id');
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

    private function countAnalyticsSessionsBetween(Carbon $start, Carbon $end): int
    {
        return AnalyticsEvent::query()
            ->where('created_at', '>=', $start)
            ->where('created_at', '<', $end)
            ->distinct('session_token')
            ->count('session_token');
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

    /**
     * @return Collection<int, array{
     *     workshop_id: string,
     *     workshop_title: string,
     *     workshop_starts_at: ?string,
     *     location_name: string,
     *     tickets_sold: int,
     *     early_bird_tickets: int
     * }>
     */
    private function topWorkshopSalesRows(Carbon $start, Carbon $end): Collection
    {
        return Ticket::query()
            ->join('workshops', 'workshops.id', '=', 'tickets.workshop_id')
            ->leftJoin('locations', 'locations.id', '=', 'workshops.location_id')
            ->whereIn('workshops.registration', self::INTERNAL_WORKSHOP_REGISTRATIONS)
            ->where('tickets.created_at', '>=', $start)
            ->where('tickets.created_at', '<', $end)
            ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
            ->selectRaw('
                workshops.id as workshop_id,
                workshops.title as workshop_title,
                workshops.starts_at as workshop_starts_at,
                workshops.location_id as workshop_location_id,
                COALESCE(locations.name, \'\') as location_name,
                COUNT(*) as tickets_sold,
                SUM(CASE WHEN tickets.is_early_bird = 1 THEN 1 ELSE 0 END) as early_bird_tickets
            ')
            ->groupBy(
                'workshops.id',
                'workshops.title',
                'workshops.starts_at',
                'workshops.location_id',
                'locations.name'
            )
            ->orderByDesc('tickets_sold')
            ->orderBy('workshops.starts_at')
            ->limit(5)
            ->get()
            ->map(function ($row): array {
                $workshopStartsAt = trim((string) ($row->workshop_starts_at ?? ''));

                return [
                    'workshop_id' => (string) $row->workshop_id,
                    'workshop_title' => (string) $row->workshop_title,
                    'workshop_starts_at' => $workshopStartsAt !== '' ? $workshopStartsAt : null,
                    'location_name' => trim((string) ($row->location_name ?? '')),
                    'tickets_sold' => (int) $row->tickets_sold,
                    'early_bird_tickets' => (int) $row->early_bird_tickets,
                ];
            })
            ->values();
    }
}
