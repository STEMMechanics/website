<?php

namespace App\Services;

use App\Models\AnalyticsEvent;
use App\Models\ContactEnquiry;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Reminder;
use App\Models\StoreOrder;
use App\Models\Ticket;
use App\Models\Workshop;
use App\Models\WorkshopInterest;
use Illuminate\Support\Carbon;

class WeeklyWorkplanService
{
    public function build(): array
    {
        $weekStart = today();
        $weekEnd = today()->endOfWeek(Carbon::SATURDAY);
        $lastStart = today()->subWeek();
        $lastEnd = today()->subDay()->endOfDay();

        $scheduledInvoices = Invoice::query()->where('scheduled_email', true)->where('status', Invoice::STATUS_DRAFT)
            ->whereBetween('issue_date', [$weekStart, $weekEnd])->with('user')->orderBy('issue_date')->get();
        $workshops = Workshop::query()->whereBetween('starts_at', [$weekStart, $weekEnd->copy()->endOfDay()])
            ->with('location')->orderBy('starts_at')->get();
        $reminders = Reminder::query()->where('status', Reminder::STATUS_PENDING)
            ->where(function ($query): void {
                $query->where('kind', '!=', ReminderService::WORKSHOP_TASK_KIND)
                    ->orWhereHasMorph('remindable', [Workshop::class], fn ($query) => $query->where('status', '!=', 'cancelled'));
            })
            ->whereBetween('scheduled_at', [$weekStart, $weekEnd->copy()->endOfDay()])->with('remindable')->orderBy('scheduled_at')->get();
        $quotes = Quote::query()->whereIn('status', [Quote::STATUS_OPEN, Quote::STATUS_AWAITING_DECISION])
            ->whereNotNull('follow_up_at')->whereDate('follow_up_at', '<=', today())
            ->with('user')->orderBy('follow_up_at')->limit(10)->get();
        $orders = StoreOrder::query()->whereIn('status', [StoreOrder::STATUS_PENDING_PAYMENT, StoreOrder::STATUS_QUOTE_REQUESTED])
            ->where('created_at', '<', now()->subDay())->with('user')->orderBy('created_at')->limit(10)->get();
        $interests = WorkshopInterest::query()->where('created_at', '>=', now()->subDays(30))->with('workshop')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')->from('tickets')
                    ->whereColumn('tickets.workshop_id', 'workshop_interests.workshop_id')
                    ->whereColumn('tickets.email', 'workshop_interests.email')
                    ->whereIn('tickets.status', Ticket::activePurchasedStatuses());
            })->orderByDesc('created_at')->limit(10)->get();
        $overdue = Invoice::query()->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_SENT, Invoice::STATUS_OVERDUE])
            ->whereDate('due_date', '<', today())->where('total_amount', '>', 0)->with('user')->orderBy('due_date')->limit(10)->get();
        $enquiries = ContactEnquiry::query()->where('created_at', '>=', now()->subDays(30))
            ->orderByDesc('created_at')->limit(10)->get();
        $pendingTransfers = Payment::query()->pendingBankTransfers()->where('received_on', '<', now()->subDays(2))
            ->with('user')->orderBy('received_on')->limit(10)->get();

        return compact('weekStart', 'weekEnd', 'scheduledInvoices', 'workshops', 'reminders', 'quotes', 'orders', 'interests', 'overdue', 'enquiries', 'pendingTransfers') + [
            'stats' => [
                'page_views' => AnalyticsEvent::query()->where('event_type', AnalyticsEvent::TYPE_PAGE_VIEW)->whereBetween('created_at', [$lastStart, $lastEnd])->count(),
                'visitors' => AnalyticsEvent::query()->whereBetween('created_at', [$lastStart, $lastEnd])->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
                'store_views' => AnalyticsEvent::query()->whereIn('route_name', ['shop.index', 'shop.product.show'])->whereBetween('created_at', [$lastStart, $lastEnd])->count(),
                'workshop_views' => AnalyticsEvent::query()->whereIn('route_name', ['workshop.index', 'workshop.show'])->whereBetween('created_at', [$lastStart, $lastEnd])->count(),
                'tickets_sold' => Ticket::query()->whereBetween('created_at', [$lastStart, $lastEnd])->whereIn('status', Ticket::activePurchasedStatuses())->count(),
                'orders' => StoreOrder::query()->whereBetween('created_at', [$lastStart, $lastEnd])->where('status', '!=', StoreOrder::STATUS_CANCELLED)->count(),
                'income' => (float) Payment::query()->where('kind', Payment::KIND_PAYMENT)->whereBetween('received_on', [$lastStart, $lastEnd])->sum('total_amount'),
                'refunds' => abs((float) Payment::query()->where('kind', Payment::KIND_REFUND)->whereBetween('received_on', [$lastStart, $lastEnd])->sum('total_amount')),
                'expenses' => (float) Expense::query()->whereBetween('paid_on', [$lastStart, $lastEnd])->sum('total_amount'),
            ],
        ];
    }
}
