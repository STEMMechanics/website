<?php

namespace App\Services;

use App\Mail\UpcomingWorkshops;
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
        $weekEnd = today()->addWeek()->endOfWeek(Carbon::SATURDAY);
        $lastStart = today()->subWeeks(2);
        $lastEnd = today()->subDay()->endOfDay();
        $previousStart = today()->subWeeks(4);
        $previousEnd = $lastStart->copy()->subDay()->endOfDay();

        $scheduledInvoices = Invoice::query()->where('scheduled_email', true)->where('status', Invoice::STATUS_DRAFT)
            ->whereBetween('issue_date', [$weekStart, $weekEnd])->with('user')->orderBy('issue_date')->get();
        $dueInvoices = Invoice::query()->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_SENT])
            ->whereBetween('due_date', [$weekStart, $weekEnd])->where('total_amount', '>', 0)
            ->with('user')->orderBy('due_date')->get();
        $workshops = Workshop::query()->whereNotIn('status', ['draft', 'cancelled'])
            ->whereBetween('starts_at', [$weekStart, $weekEnd->copy()->endOfDay()])
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
        $newsletter = $this->newsletterPreview();
        $stats = [
            'page_views' => AnalyticsEvent::query()->where('event_type', AnalyticsEvent::TYPE_PAGE_VIEW)->whereBetween('created_at', [$lastStart, $lastEnd])->count(),
            'visitors' => AnalyticsEvent::query()->whereBetween('created_at', [$lastStart, $lastEnd])->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
            'store_views' => AnalyticsEvent::query()->whereIn('route_name', ['shop.index', 'shop.product.show'])->whereBetween('created_at', [$lastStart, $lastEnd])->count(),
            'workshop_views' => AnalyticsEvent::query()->whereIn('route_name', ['workshop.index', 'workshop.show'])->whereBetween('created_at', [$lastStart, $lastEnd])->count(),
            'tickets_sold' => Ticket::query()->whereBetween('created_at', [$lastStart, $lastEnd])->whereIn('status', Ticket::activePurchasedStatuses())->count(),
            'orders' => StoreOrder::query()->whereBetween('created_at', [$lastStart, $lastEnd])->where('status', '!=', StoreOrder::STATUS_CANCELLED)->count(),
            'income' => (float) Payment::query()->where('kind', Payment::KIND_PAYMENT)->whereBetween('received_on', [$lastStart, $lastEnd])->sum('total_amount'),
            'refunds' => abs((float) Payment::query()->where('kind', Payment::KIND_REFUND)->whereBetween('received_on', [$lastStart, $lastEnd])->sum('total_amount')),
            'expenses' => (float) Expense::query()->whereBetween('paid_on', [$lastStart, $lastEnd])->sum('total_amount'),
        ];
        $previousWebsiteStats = [
            'page_views' => AnalyticsEvent::query()->where('event_type', AnalyticsEvent::TYPE_PAGE_VIEW)->whereBetween('created_at', [$previousStart, $previousEnd])->count(),
            'visitors' => AnalyticsEvent::query()->whereBetween('created_at', [$previousStart, $previousEnd])->whereNotNull('visitor_hash')->distinct('visitor_hash')->count('visitor_hash'),
            'store_views' => AnalyticsEvent::query()->whereIn('route_name', ['shop.index', 'shop.product.show'])->whereBetween('created_at', [$previousStart, $previousEnd])->count(),
            'workshop_views' => AnalyticsEvent::query()->whereIn('route_name', ['workshop.index', 'workshop.show'])->whereBetween('created_at', [$previousStart, $previousEnd])->count(),
        ];
        $websiteChanges = collect($previousWebsiteStats)->mapWithKeys(fn (int $previous, string $key): array => [
            $key => $this->percentageChange((int) $stats[$key], $previous),
        ])->all();

        return compact('weekStart', 'weekEnd', 'scheduledInvoices', 'dueInvoices', 'workshops', 'reminders', 'quotes', 'orders', 'interests', 'overdue', 'enquiries', 'pendingTransfers', 'newsletter') + [
            'stats' => $stats,
            'websiteChanges' => $websiteChanges,
        ];
    }

    /** @return array{percentage: float, label: string, direction: string} */
    private function percentageChange(int $current, int $previous): array
    {
        $percentage = $previous === 0
            ? ($current === 0 ? 0.0 : 100.0)
            : round((($current - $previous) / $previous) * 100, 1);
        $direction = $percentage > 0 ? 'growth' : ($percentage < 0 ? 'decline' : 'neutral');

        return [
            'percentage' => $percentage,
            'label' => $direction === 'neutral'
                ? '0% - no change'
                : number_format(abs($percentage), 1).'% '.$direction,
            'direction' => $direction,
        ];
    }

    /** @return array<string, mixed> */
    private function newsletterPreview(): array
    {
        $sendAt = now()->copy()->setTime(16, 0);
        if ($sendAt->dayOfWeek !== Carbon::WEDNESDAY || $sendAt->isPast()) {
            $sendAt->next(Carbon::WEDNESDAY)->setTime(16, 0);
        }

        $newsletter = new UpcomingWorkshops('', 'Upcoming Workshops 🌟');
        $workshops = $newsletter->workshops->concat($newsletter->onlineWorkshops)->sortBy('starts_at')->values();
        $storeSections = collect($newsletter->storePromotion['sections'] ?? []);
        $workshopSection = collect([[
            'type' => 'workshops',
            'title' => 'Workshops',
            'items' => $workshops,
        ]]);
        $productSections = $storeSections->map(fn (array $section): array => [
            'type' => 'store',
            'title' => $section['title'],
            'items' => collect($section['products'] ?? []),
        ]);

        return [
            'sendAt' => $sendAt,
            'subject' => $newsletter->heroSubject ?: $newsletter->subject,
            'heading' => $newsletter->heroHeader,
            'introduction' => $newsletter->heroCta,
            'workshops' => $workshops,
            'storeSections' => $storeSections,
            'contentSections' => $newsletter->contentOrder === 'store'
                ? $productSections->concat($workshopSection)
                : $workshopSection->concat($productSections),
        ];
    }
}
