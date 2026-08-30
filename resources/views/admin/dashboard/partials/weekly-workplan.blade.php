@php
    $followUpCount = $workplan['quotes']->count()
        + $workplan['orders']->count()
        + $workplan['interests']->count()
        + $workplan['enquiries']->count()
        + $workplan['overdue']->count()
        + $workplan['pendingTransfers']->count();
@endphp

<details open class="group rounded-2xl border border-gray-200 bg-white shadow-sm">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 [&::-webkit-details-marker]:hidden">
        <div>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chevron-right text-xs text-gray-400 transition-transform group-open:rotate-90"></i>
                <h2 class="text-lg font-semibold text-gray-900">Weekly Workplan</h2>
            </div>
            <p class="mt-1 pl-5 text-sm text-gray-500">Live view for {{ $workplan['weekStart']->format('D j M') }}–{{ $workplan['weekEnd']->format('D j M Y') }}. This is the same information included in Sunday’s email.</p>
        </div>
        @if($followUpCount > 0)
            <span class="shrink-0 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-800">{{ $followUpCount }} follow-up{{ $followUpCount === 1 ? '' : 's' }}</span>
        @endif
    </summary>

    <div class="border-t border-gray-100 p-5">
        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-xl border border-sky-100 bg-sky-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Scheduled invoices</div><div class="mt-1 text-2xl font-bold text-sky-950">{{ $workplan['scheduledInvoices']->count() }}</div></div>
            <div class="rounded-xl border border-violet-100 bg-violet-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-violet-700">Workshops</div><div class="mt-1 text-2xl font-bold text-violet-950">{{ $workplan['workshops']->count() }}</div></div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Tasks and reminders</div><div class="mt-1 text-2xl font-bold text-emerald-950">{{ $workplan['reminders']->count() }}</div></div>
            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Follow-ups</div><div class="mt-1 text-2xl font-bold text-amber-950">{{ $followUpCount }}</div></div>
        </div>

        <div class="mt-5 grid gap-5 xl:grid-cols-2">
            <section>
                <h3 class="font-semibold text-gray-900">Coming up this week</h3>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach($workplan['scheduledInvoices'] as $invoice)
                        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-solid fa-file-invoice-dollar mt-0.5 w-4 text-sky-600"></i><span class="min-w-0"><span class="block font-semibold text-gray-900">{{ $invoice->invoice_number }} · {{ $invoice->user?->getName() ?: $invoice->billing_name }}</span><span class="text-xs text-gray-500">Emails {{ $invoice->issue_date?->format('D j M') }} · {{ money((float) $invoice->total_amount) }}</span></span></a>
                    @endforeach
                    @foreach($workplan['workshops'] as $workshop)
                        <a href="{{ route('workshop.show', $workshop) }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-solid fa-bullhorn mt-0.5 w-4 text-violet-600"></i><span class="min-w-0"><span class="block font-semibold text-gray-900">{{ $workshop->title }}</span><span class="text-xs text-gray-500">{{ $workshop->starts_at?->format('D j M, g:ia') }}</span></span></a>
                    @endforeach
                    @foreach($workplan['reminders'] as $reminder)
                        <a href="{{ $reminder->action_url ?: '#' }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-regular fa-bell mt-0.5 w-4 text-emerald-600"></i><span class="min-w-0"><span class="block font-semibold text-gray-900">{{ $reminder->subject }}</span><span class="text-xs text-gray-500">{{ $reminder->scheduled_at?->format('D j M, g:ia') }}</span></span></a>
                    @endforeach
                    @if($workplan['scheduledInvoices']->isEmpty() && $workplan['workshops']->isEmpty() && $workplan['reminders']->isEmpty())
                        <p class="p-4 text-sm text-gray-500">Nothing is currently scheduled for the rest of this week.</p>
                    @endif
                </div>
            </section>

            <section>
                <h3 class="font-semibold text-gray-900">Suggested follow-ups</h3>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach($workplan['quotes'] as $quote)
                        <a href="{{ route('admin.quote.edit', $quote) }}" class="block p-3 text-sm hover:bg-gray-50"><span class="font-semibold text-gray-900">Quote {{ $quote->quote_number }} · {{ $quote->user?->getName() }}</span><span class="block text-xs text-gray-500">Open · last updated {{ $quote->updated_at->diffForHumans() }}</span></a>
                    @endforeach
                    @foreach($workplan['orders'] as $order)
                        <a href="{{ route('admin.shop.order.edit', $order) }}" class="block p-3 text-sm hover:bg-gray-50"><span class="font-semibold text-gray-900">Order {{ $order->order_number }} · {{ $order->user?->getName() ?: $order->billing_name }}</span><span class="block text-xs text-gray-500">{{ str($order->status)->replace('_', ' ')->title() }} · {{ money((float) $order->total_amount) }}</span></a>
                    @endforeach
                    @foreach($workplan['overdue'] as $invoice)
                        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="block p-3 text-sm hover:bg-gray-50"><span class="font-semibold text-gray-900">Overdue invoice {{ $invoice->invoice_number }} · {{ $invoice->user?->getName() ?: $invoice->billing_name }}</span><span class="block text-xs text-red-600">{{ money((float) $invoice->displayOutstandingAmount()) }} outstanding · due {{ $invoice->due_date?->format('j M') }}</span></a>
                    @endforeach
                    @foreach($workplan['pendingTransfers'] as $payment)
                        <a href="{{ route('admin.payment.edit', $payment) }}" class="block p-3 text-sm hover:bg-gray-50"><span class="font-semibold text-gray-900">Pending transfer · {{ $payment->user?->getName() ?: 'Unknown customer' }}</span><span class="block text-xs text-gray-500">{{ money((float) $payment->total_amount) }} · received {{ $payment->received_on?->diffForHumans() }}</span></a>
                    @endforeach
                    @foreach($workplan['interests'] as $interest)
                        <a href="mailto:{{ $interest->email }}" class="block p-3 text-sm hover:bg-gray-50"><span class="font-semibold text-gray-900">Workshop interest · {{ $interest->name }}</span><span class="block text-xs text-gray-500">{{ $interest->workshop?->title }} · {{ $interest->email }}</span></a>
                    @endforeach
                    @foreach($workplan['enquiries'] as $enquiry)
                        <a href="mailto:{{ $enquiry->email }}" class="block p-3 text-sm hover:bg-gray-50"><span class="font-semibold text-gray-900">Website enquiry · {{ $enquiry->name }}</span><span class="block text-xs text-gray-500">{{ $enquiry->subject }} · {{ $enquiry->created_at->diffForHumans() }}</span></a>
                    @endforeach
                    @if($followUpCount === 0)
                        <p class="p-4 text-sm text-gray-500">No follow-ups are currently suggested.</p>
                    @endif
                </div>
            </section>
        </div>

    </div>
</details>
