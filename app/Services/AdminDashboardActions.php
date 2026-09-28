<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\StoreOrder;
use App\Models\Ticket;
use App\Models\Workshop;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class AdminDashboardActions
{
    /** @return list<array<string, mixed>> */
    public function build(?string $userId = null): array
    {
        $now = now();
        $dynamic = $this->attendanceActions($now);

        $orderCount = StoreOrder::query()->where(function (Builder $query) use ($now): void {
            $query->whereIn('status', StoreOrder::ACTION_REQUIRED_STATUSES)
                ->orWhere(fn (Builder $pending): Builder => $pending
                    ->where('status', StoreOrder::STATUS_PENDING_PAYMENT)
                    ->where('created_at', '<', $now->copy()->subDay()));
        })->count();
        if ($orderCount > 0) {
            $dynamic[] = $this->card(
                'Process store orders',
                $orderCount.' '.($orderCount === 1 ? 'store order needs' : 'store orders need').' a follow-up.',
                route('admin.shop.order.index'),
                'fa-solid fa-box-open',
                'amber',
            );
        }

        $date = $now->copy();
        if ($date->day >= 25 || $date->day <= 10) {
            $basMonth = $date->copy()->subMonthNoOverflow();
            $actionKey = 'bas:'.$basMonth->format('Y-m');
            if (! $userId || ! $this->isDismissed($userId, $actionKey)) {
                $dynamic[] = $this->card(
                    'Review BAS · '.$basMonth->format('M Y'),
                    'Check expenses and customer payments for '.$basMonth->format('F Y').'.',
                    route('admin.bas.index', ['month' => $basMonth->format('Y-m')]),
                    'fa-solid fa-file-invoice-dollar',
                    'emerald',
                    $actionKey,
                );
            }
        }

        $overdueInvoices = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])
            ->whereDate('due_date', '<', $now->toDateString())
            ->where('total_amount', '>', 0)
            ->count();
        if ($overdueInvoices > 0) {
            $dynamic[] = $this->card(
                'Follow up overdue invoices',
                $overdueInvoices.' '.($overdueInvoices === 1 ? 'invoice is' : 'invoices are').' past its due date.',
                route('admin.invoice.index'),
                'fa-solid fa-file-invoice',
                'pink',
            );
        }

        $common = [
            $this->card('Add an expense', 'Record a purchase and attach its receipt.', route('admin.expense.create'), 'fa-solid fa-receipt', 'emerald'),
            $this->card('Create a workshop', 'Set up a new workshop or course.', route('admin.workshop.create'), 'fa-solid fa-calendar-plus', 'violet'),
            $this->card('Manage workshops', 'View schedules, bookings, and attendance.', route('admin.workshop.index'), 'fa-solid fa-calendar-days', 'violet'),
            $this->card('Store orders', 'Review orders and update their progress.', route('admin.shop.order.index'), 'fa-solid fa-box-open', 'amber'),
            $this->card('Manage invoices', 'Create invoices and review payments due.', route('admin.invoice.index'), 'fa-solid fa-file-invoice-dollar', 'sky'),
        ];

        // Fill up to two balanced rows, preferring current work and avoiding duplicate links.
        $selected = [];
        foreach ([...$dynamic, ...$common] as $card) {
            if (count($selected) >= 8) {
                break;
            }
            if (! collect($selected)->contains(fn (array $selectedCard): bool => $selectedCard['url'] === $card['url'])) {
                $selected[] = $card;
            }
        }

        return $selected;
    }

    public function dismissAction(string $userId, string $actionKey): bool
    {
        $date = now();
        if (! preg_match('/^bas:\d{4}-(0[1-9]|1[0-2])$/', $actionKey)
            || ($date->day < 25 && $date->day > 10)
            || $actionKey !== 'bas:'.$date->copy()->subMonthNoOverflow()->format('Y-m')) {
            return false;
        }

        $now = now();
        DB::table('admin_dashboard_action_dismissals')->updateOrInsert(
            ['user_id' => $userId, 'action_key' => $actionKey],
            ['dismissed_at' => $now, 'updated_at' => $now, 'created_at' => $now],
        );

        return true;
    }

    private function isDismissed(string $userId, string $actionKey): bool
    {
        return DB::table('admin_dashboard_action_dismissals')
            ->where('user_id', $userId)
            ->where('action_key', $actionKey)
            ->exists();
    }

    /** @return list<array<string, mixed>> */
    private function attendanceActions(Carbon $now): array
    {
        $lookback = $now->copy()->subDays(14);
        $soon = $now->copy()->addDay();
        $workshops = Workshop::query()
            ->where('registration', 'tickets')
            ->whereNotIn('status', ['draft', 'cancelled'])
            ->whereNotNull('starts_at')
            ->where('starts_at', '<=', $soon)
            ->where(fn (Builder $query): Builder => $query->whereNull('ends_at')->orWhere('ends_at', '>=', $lookback))
            ->whereHas('tickets', fn (Builder $tickets): Builder => $tickets->whereIn('status', Ticket::activePurchasedStatuses()))
            ->with(['location', 'tickets' => fn ($tickets) => $tickets
                ->whereIn('status', Ticket::activePurchasedStatuses())
                ->select(['id', 'workshop_id', 'attended_at'])])
            ->orderBy('starts_at')
            ->limit(150)
            ->get();

        if ($workshops->isEmpty()) {
            return [];
        }

        $courseAttendance = DB::table('workshop_session_attendance as attendance')
            ->join('tickets', 'tickets.id', '=', 'attendance.ticket_id')
            ->whereIn('attendance.workshop_id', $workshops->pluck('id'))
            ->whereIn('tickets.status', Ticket::activePurchasedStatuses())
            ->select('attendance.workshop_id', 'attendance.session_id')
            ->selectRaw('COUNT(DISTINCT attendance.ticket_id) AS attendee_count')
            ->groupBy('attendance.workshop_id', 'attendance.session_id')
            ->get()
            ->mapWithKeys(fn (object $row): array => [
                $row->workshop_id.'|'.$row->session_id => (int) $row->attendee_count,
            ]);

        $actions = [];
        foreach ($workshops as $workshop) {
            $tickets = $workshop->tickets;
            $ticketCount = $tickets->count();
            if ($ticketCount === 0) {
                continue;
            }

            if ($workshop->isCourse()) {
                foreach ($workshop->effectiveScheduleEntries() as $session) {
                    if (! isset($session['starts_at'], $session['ends_at'], $session['id'])) {
                        continue;
                    }
                    $startsAt = Carbon::parse($session['starts_at']);
                    $endsAt = Carbon::parse($session['ends_at']);
                    if ($startsAt->gt($soon) || $endsAt->lt($lookback)) {
                        continue;
                    }
                    $attended = (int) ($courseAttendance[$workshop->id.'|'.$session['id']] ?? 0);
                    if ($endsAt->lte($now) && $attended >= $ticketCount) {
                        continue;
                    }
                    $actions[] = $this->attendanceCard($workshop, $startsAt, $endsAt, $ticketCount, $attended, (string) $session['id']);
                }

                continue;
            }

            $startsAt = $workshop->starts_at;
            $endsAt = $workshop->ends_at ?? $startsAt;
            if (! $startsAt || ! $endsAt || $startsAt->gt($soon) || $endsAt->lt($lookback)) {
                continue;
            }
            $attended = $tickets->filter(fn (Ticket $ticket): bool => $ticket->attended_at !== null)->count();
            if ($endsAt->lte($now) && $attended >= $ticketCount) {
                continue;
            }
            $actions[] = $this->attendanceCard($workshop, $startsAt, $endsAt, $ticketCount, $attended);
        }

        usort($actions, function (array $left, array $right): int {
            return ($right['_priority'] <=> $left['_priority']) ?: ($left['_sort'] <=> $right['_sort']);
        });

        return array_map(fn (array $action): array => array_diff_key($action, ['_priority' => true, '_sort' => true]), array_slice($actions, 0, 2));
    }

    /** @return array<string, mixed> */
    private function attendanceCard(Workshop $workshop, Carbon $startsAt, Carbon $endsAt, int $ticketCount, int $attended, ?string $sessionId = null): array
    {
        $now = now();
        if ($endsAt->lte($now)) {
            $description = $attended.'/'.$ticketCount.' marked';
            $phase = 'Needs attendance';
            $priority = $endsAt->gte($now->copy()->subDay()) ? 130 : 115;
        } elseif ($startsAt->lte($now)) {
            $description = 'Under way · '.$attended.'/'.$ticketCount.' marked';
            $phase = 'Under way';
            $priority = 125;
        } else {
            $description = 'Upcoming · '.$attended.'/'.$ticketCount.' marked';
            $phase = 'Upcoming';
            $priority = $startsAt->diffInHours($now) <= 3 ? 110 : 100;
        }
        $location = $workshop->getLocationName();
        $schedule = $startsAt->format('D j M, g:i a');
        $description = $workshop->title.' · '.$schedule.' · '.$location.' · '.$description;

        $parameters = ['workshop' => $workshop];
        if ($sessionId !== null) {
            $parameters['session_id'] = $sessionId;
        }

        return $this->card(
            'Mark Attendance',
            $description,
            route('admin.workshop.attendance', $parameters),
            'fa-solid fa-clipboard-check',
            'violet',
            null,
            true,
        ) + [
            'attendance_details' => [
                'workshop' => (string) $workshop->title,
                'schedule' => $schedule,
                'location' => $location,
                'phase' => $phase,
                'attended' => $attended,
                'total' => $ticketCount,
            ],
            '_priority' => $priority,
            '_sort' => $endsAt->getTimestamp(),
        ];
    }

    /** @return array<string, mixed> */
    private function card(string $title, string $description, string $url, string $icon, string $tone = 'sky', ?string $dismissKey = null, bool $titleNoWrap = false): array
    {
        return [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'icon' => $icon,
            'tone' => $tone,
            'dismiss_key' => $dismissKey,
            'title_no_wrap' => $titleNoWrap,
        ];
    }
}
