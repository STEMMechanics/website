@php
    $followUpCount = $workplan['quotes']->count()
        + $workplan['orders']->count()
        + $workplan['interests']->count()
        + $workplan['enquiries']->count()
        + $workplan['overdue']->count()
        + $workplan['pendingTransfers']->count();
    $outstandingReminderCount = $workplan['reminders']
        ->reject(fn ($reminder) => $reminder->isCompletedWorkshopTask())
        ->count();
@endphp

<details open class="w-full group rounded-2xl border border-gray-200 bg-white shadow-sm">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 [&::-webkit-details-marker]:hidden">
        <div>
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-chevron-right text-xs text-gray-400 transition-transform group-open:rotate-90"></i>
                <h2 class="text-lg font-semibold text-gray-900">Fortnightly Workplan</h2>
            </div>
            <p class="mt-1 pl-4 text-sm text-gray-500">{{ $workplan['weekStart']->format('D j M') }} to {{ $workplan['weekEnd']->format('D j M Y') }}.</p>
        </div>
        <div class="flex items-center gap-2" onclick="event.stopPropagation()">
            <a href="{{ route('admin.dashboard.workplan.pdf') }}" target="_blank" rel="noopener noreferrer" class="flex size-11 items-center justify-center text-2xl text-gray-700 transition hover:text-primary-color" title="Open printable PDF" aria-label="Open printable PDF"><i class="fa-regular fa-file-pdf" aria-hidden="true"></i></a>
        </div>
    </summary>

    <div class="border-t border-gray-100 p-5">
        <div class="grid gap-5 md:grid-cols-2">
            <section class="grid self-start grid-cols-2 gap-3 md:col-span-2 md:grid-cols-5">
                <div class="flex justify-between items-center rounded-xl border border-sky-100 bg-sky-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-sky-700">Scheduled invoices</div><div class="text-3xl font-bold text-sky-700">{{ $workplan['scheduledInvoices']->count() }}</div></div>
                <div class="flex justify-between items-center rounded-xl border border-pink-100 bg-pink-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-pink-700">Invoices due</div><div class="text-3xl font-bold text-pink-700">{{ $workplan['dueInvoices']->count() }}</div></div>
                <div class="flex justify-between items-center rounded-xl border border-violet-100 bg-violet-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-violet-700">Workshops</div><div class="text-3xl font-bold text-violet-700">{{ $workplan['workshops']->count() }}</div></div>
                <div class="flex justify-between items-center rounded-xl border border-emerald-100 bg-emerald-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Tasks / reminders</div><div class="text-3xl font-bold text-emerald-700">{{ $outstandingReminderCount }}</div></div>
                <div class="flex justify-between items-center rounded-xl border border-amber-100 bg-amber-50 p-4"><div class="text-xs font-semibold uppercase tracking-wide text-amber-700">Follow-ups</div><div class="text-3xl font-bold text-amber-700">{{ $followUpCount }}</div></div>
            </section>

            <div class="space-y-5">
            <section>
                <h3 class="font-semibold text-gray-900">Coming up this fortnight</h3>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach($workplan['scheduledInvoices'] as $invoice)
                        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-solid fa-paper-plane mt-0.5 w-4 text-sky-600"></i><span class="min-w-0 flex-1"><span class="block font-semibold text-gray-900">Invoice email scheduled · {{ $invoice->invoice_number }}</span><span class="text-xs text-gray-500">Sends {{ $invoice->issue_date?->format('D j M') }} to {{ $invoice->user?->getName() ?: $invoice->billing_name }} · {{ money((float) $invoice->total_amount) }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['dueInvoices'] as $invoice)
                        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-solid fa-calendar-check mt-0.5 w-4 text-gray-500"></i><span class="min-w-0 flex-1"><span class="block font-semibold text-gray-900">Payment due · {{ $invoice->invoice_number }} · {{ $invoice->user?->getName() ?: $invoice->billing_name }}</span><span class="text-xs text-gray-500">Due {{ $invoice->due_date?->format('D j M') }} · {{ money((float) $invoice->displayOutstandingAmount()) }} outstanding</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['workshops'] as $workshop)
                        @php($workshopLocation = trim((string) $workshop->getLocationName()))
                        <a href="{{ route('workshop.show', $workshop) }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-solid fa-bullhorn mt-0.5 w-4 text-violet-600"></i><span class="min-w-0 flex-1"><span class="block font-semibold text-gray-900">{{ $workshop->title }}</span><span class="text-xs text-gray-500">{{ $workshop->starts_at?->format('D j M, g:ia') }}{{ $workshopLocation !== '' ? ' · '.$workshopLocation : '' }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['reminders'] as $reminder)
                        @php($reminderTaskName = (string) str($reminder->subject)->after('Workshop task: ')->before(' — '))
                        @php($reminderWorkshopName = $reminder->remindable instanceof \App\Models\Workshop ? $reminder->remindable->title : '')
                        @php($reminderWorkshopLocation = $reminder->remindable instanceof \App\Models\Workshop ? trim((string) $reminder->remindable->getLocationName()) : '')
                        @php($reminderCompleted = $reminder->isCompletedWorkshopTask())
                        <a href="{{ $reminder->action_url ?: '#' }}" class="flex items-start gap-3 p-3 text-sm hover:bg-gray-50"><i class="fa-regular fa-bell mt-0.5 w-4 {{ $reminderCompleted ? 'text-gray-400' : 'text-emerald-600' }}"></i><span class="min-w-0 flex-1 {{ $reminderCompleted ? 'text-gray-400 line-through' : '' }}"><span class="block font-semibold {{ $reminderCompleted ? '' : 'text-gray-900' }}">{{ $reminderWorkshopName !== '' ? $reminderWorkshopName.' · '.$reminderTaskName : $reminder->subject }}</span><span class="text-xs {{ $reminderCompleted ? '' : 'text-gray-500' }}">{{ $reminder->scheduled_at?->format('D j M, g:ia') }}{{ $reminderWorkshopLocation !== '' ? ' · '.$reminderWorkshopLocation : '' }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @if($workplan['scheduledInvoices']->isEmpty() && $workplan['dueInvoices']->isEmpty() && $workplan['workshops']->isEmpty() && $workplan['reminders']->isEmpty())
                        <p class="p-4 text-sm text-gray-500">Nothing is currently scheduled for the rest of this fortnight.</p>
                    @endif
                </div>
            </section>

            <section>
                <h3 class="font-semibold text-gray-900">Next newsletter</h3>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    <div class="flex items-start gap-3 p-3 text-sm">
                        <i class="fa-regular fa-envelope mt-0.5 w-4 text-indigo-600"></i>
                        <span class="min-w-0 flex-1">
                            <span class="block font-semibold text-gray-900">Subject: {{ $workplan['newsletter']['subject'] }}</span>
                            <span class="text-xs text-gray-500">Sends {{ $workplan['newsletter']['sendAt']->format('D j M, g:ia') }} · {{ $workplan['newsletter']['heading'] }}</span>
                        </span>
                    </div>
                    @foreach($workplan['newsletter']['contentSections'] as $section)
                        <div class="flex items-start gap-3 p-3 text-sm">
                            <i class="fa-solid {{ $section['type'] === 'workshops' ? 'fa-bullhorn text-violet-600' : 'fa-bag-shopping text-emerald-600' }} mt-0.5 w-4"></i>
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-gray-900">{{ $section['title'] }}</span>
                                <ul class="mt-1 list-disc space-y-1 pl-4 text-xs text-gray-500">
                                    @forelse($section['items'] as $item)
                                        @if($section['type'] === 'workshops')
                                            @php($newsletterWorkshopLocation = trim((string) $item->getLocationName()))
                                            <li><a href="{{ route('admin.workshop.edit', $item) }}" class="hover:text-primary-color hover:underline">{{ $item->title }} · {{ $item->starts_at?->format('D j M, g:ia') }}{{ $newsletterWorkshopLocation !== '' ? ' · '.$newsletterWorkshopLocation : '' }}</a></li>
                                        @else
                                            <li>{{ $item->title }}</li>
                                        @endif
                                    @empty
                                        <li>No items selected</li>
                                    @endforelse
                                </ul>
                            </span>
                        </div>
                    @endforeach
                    <a href="{{ route('admin.subscription.index') }}" class="flex items-center gap-3 p-3 text-sm hover:bg-gray-50">
                        <i class="fa-solid fa-pen-to-square w-4 text-indigo-600"></i>
                        <span class="min-w-0 flex-1 font-semibold text-primary-color">Review newsletter</span>
                        <i class="fa-solid fa-arrow-up-right-from-square text-xs text-gray-400" aria-hidden="true"></i>
                    </a>
                </div>
            </section>
            </div>

            <div class="space-y-5">
            <section>
                <h3 class="font-semibold text-gray-900">Suggested follow-ups</h3>
                <div class="mt-2 divide-y divide-gray-100 rounded-xl border border-gray-200">
                    @foreach($workplan['quotes'] as $quote)
                        <div class="flex items-start gap-2 p-3 text-sm hover:bg-gray-50">
                            <a href="{{ route('admin.quote.edit', $quote) }}" class="flex min-w-0 flex-1 items-start gap-2"><span class="min-w-0 flex-1"><span class="font-semibold text-gray-900">Quote {{ $quote->quote_number }} · {{ $quote->user?->getName() }}</span><span class="block text-xs text-gray-500">{{ $quote->statusLabel() }} · follow-up due {{ $quote->follow_up_at?->format('j M Y') }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                            <form method="POST" action="{{ route('admin.quote.snooze-follow-up', $quote) }}" class="shrink-0">
                                @csrf
                                <button type="submit" class="flex size-5 items-center justify-center text-xs text-gray-400 transition hover:text-primary-color" title="Snooze follow-up for 7 days" aria-label="Snooze follow-up for 7 days"><i class="fa-solid fa-clock" aria-hidden="true"></i></button>
                            </form>
                        </div>
                    @endforeach
                    @foreach($workplan['orders'] as $order)
                        <a href="{{ route('admin.shop.order.edit', $order) }}" class="flex items-start gap-2 p-3 text-sm hover:bg-gray-50"><span class="min-w-0 flex-1"><span class="font-semibold text-gray-900">Order {{ $order->order_number }} · {{ $order->user?->getName() ?: $order->billing_name }}</span><span class="block text-xs text-gray-500">{{ str($order->status)->replace('_', ' ')->title() }} · {{ money((float) $order->total_amount) }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['overdue'] as $invoice)
                        <a href="{{ route('admin.invoice.edit', $invoice) }}" class="flex items-start gap-2 p-3 text-sm hover:bg-gray-50"><span class="min-w-0 flex-1"><span class="font-semibold text-gray-900">Overdue invoice {{ $invoice->invoice_number }} · {{ $invoice->user?->getName() ?: $invoice->billing_name }}</span><span class="block text-xs text-red-600">{{ money((float) $invoice->displayOutstandingAmount()) }} outstanding · due {{ $invoice->due_date?->format('j M') }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['pendingTransfers'] as $payment)
                        <a href="{{ route('admin.payment.edit', $payment) }}" class="flex items-start gap-2 p-3 text-sm hover:bg-gray-50"><span class="min-w-0 flex-1"><span class="font-semibold text-gray-900">Pending transfer · {{ $payment->user?->getName() ?: 'Unknown customer' }}</span><span class="block text-xs text-gray-500">{{ money((float) $payment->total_amount) }} · received {{ $payment->received_on?->diffForHumans() }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['interests'] as $interest)
                        <a href="mailto:{{ $interest->email }}" class="flex items-start gap-2 p-3 text-sm hover:bg-gray-50"><span class="min-w-0 flex-1"><span class="font-semibold text-gray-900">Workshop interest · {{ $interest->name }}</span><span class="block text-xs text-gray-500">{{ $interest->workshop?->title }} · {{ $interest->email }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @foreach($workplan['enquiries'] as $enquiry)
                        <a href="mailto:{{ $enquiry->email }}" class="flex items-start gap-2 p-3 text-sm hover:bg-gray-50"><span class="min-w-0 flex-1"><span class="font-semibold text-gray-900">Website enquiry · {{ $enquiry->name }}</span><span class="block text-xs text-gray-500">{{ $enquiry->subject }} · {{ $enquiry->created_at->diffForHumans() }}</span></span><i class="fa-solid fa-arrow-up-right-from-square mt-1 text-xs text-gray-400" aria-hidden="true"></i></a>
                    @endforeach
                    @if($followUpCount === 0)
                        <p class="p-4 text-sm text-gray-500">No follow-ups are currently suggested.</p>
                    @endif
                </div>
            </section>

            <section>
                <h3 class="font-semibold text-gray-900">Website last fortnight</h3>
                <div class="mt-2 grid grid-cols-2 gap-3 rounded-xl border border-gray-200 p-3">
                    @foreach([
                        'page_views' => 'Page views',
                        'visitors' => 'Unique visitors',
                        'store_views' => 'Store views',
                        'workshop_views' => 'Workshop views',
                    ] as $statKey => $statLabel)
                        @php($change = $workplan['websiteChanges'][$statKey])
                        <div class="rounded-lg bg-gray-50 p-3">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">{{ $statLabel }}</div>
                            <div class="mt-1 text-2xl font-bold text-gray-900">{{ number_format($workplan['stats'][$statKey]) }}</div>
                            <div class="mt-1 text-xs font-semibold {{ $change['direction'] === 'growth' ? 'text-emerald-700' : ($change['direction'] === 'decline' ? 'text-rose-700' : 'text-gray-500') }}">{{ $change['label'] }}</div>
                        </div>
                    @endforeach
                </div>
            </section>
            </div>
        </div>

    </div>
</details>
