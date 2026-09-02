<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Fortnightly Workplan</title>
    <style>
        @page { margin: 30px; size: A4; }
        @font-face { font-family: 'Poppins'; font-style: normal; font-weight: 400; src: url('{{ resource_path('fonts/Poppins-Regular.ttf') }}') format('truetype'); }
        @font-face { font-family: 'Poppins'; font-style: normal; font-weight: 700; src: url('{{ resource_path('fonts/Poppins-Bold.ttf') }}') format('truetype'); }
        body { font-family: 'Poppins', sans-serif; color: #333; font-size: 8.5px; line-height: 1.35; }
        table { width: 100%; border-collapse: collapse; border-spacing: 0; }
        td { padding: 0; }
        .header { margin-bottom: 14px; }
        .logo-wrap { width: 31%; vertical-align: top; }
        .logo { width: 180px; height: auto; margin-top: 4px; }
        .company { width: 25%; color: #555; font-size: 8px; line-height: .95; vertical-align: top; }
        .headline { width: 44%; color: #333; font-size: 19px; font-weight: 700; line-height: .95; text-align: right; vertical-align: bottom; }
        .headline .underline { text-decoration: underline; text-decoration-color: #1da1e6; }
        .period { margin: 0 0 12px; padding: 7px 10px; border-top: 1px solid #d6d6d6; color: #1da1e6; font-size: 10px; font-weight: 700; text-align: right; }
        h2 { margin: 14px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #1da1e6; color: #1da1e6; font-size: 12px; text-transform: uppercase; }
        h3 { margin: 8px 0 3px; color: #334155; font-size: 9px; }
        p { margin: 3px 0; }
        ul { margin: 3px 0 6px; padding-left: 15px; }
        li { margin: 1.5px 0; }
        .muted { color: #64748b; }
        .completed { color: #94a3b8; text-decoration: line-through; }
        .summary { table-layout: fixed; margin-bottom: 12px; }
        .summary td { width: 20%; padding-bottom: 10px; border: 3px solid #fff; text-align: center; }
        .summary strong { display: block; font-size: 24px; }
        .summary span { font-size: 7.5px; font-weight: 700; text-transform: uppercase; }
        .sky { background: #e0f2fe; color: #0369a1; }
        .pink { background: #fce7f3; color: #be185d; }
        .violet { background: #ede9fe; color: #6d28d9; }
        .emerald { background: #d1fae5; color: #047857; }
        .amber { background: #fef3c7; color: #b45309; }
        .section { page-break-inside: avoid; }
        .content-grid { table-layout: fixed; }
        .content-grid > tbody > tr > td { width: 50%; vertical-align: top; }
        .content-grid .left-column { padding-right: 5px; }
        .content-grid .right-column { padding-left: 5px; }
        .coming { padding: 8px 10px; border-left: 4px solid #1da1e6; background: #f8fafc; }
        .coming h2 { margin-top: 0; }
        .website { margin-top: 10px; padding: 8px 10px; border-left: 4px solid #64748b; background: #f8fafc; }
        .website h2 { margin-top: 0; border-color: #94a3b8; color: #475569; }
        .website-stats { table-layout: fixed; }
        .website-stats td { width: 50%; padding: 4px; vertical-align: top; }
        .website-stat { padding: 6px; background: #f1f5f9; }
        .website-stat strong { display: block; color: #0f172a; font-size: 13px; }
        .website-stat span { color: #64748b; font-size: 7px; font-weight: 700; text-transform: uppercase; }
        .website-stat small { display: block; margin-top: 2px; font-size: 7px; font-weight: 700; }
        .growth { color: #047857; }
        .decline { color: #be123c; }
        .neutral { color: #64748b; }
        .newsletter { padding: 8px 10px; border-left: 4px solid #6d28d9; background: #f8fafc; }
        .newsletter h2 { margin-top: 0; border-color: #8b5cf6; color: #6d28d9; }
        .followups { margin-top: 10px; padding: 8px 10px; border-left: 4px solid #f59e0b; background: #f8fafc; }
        .followups h2 { margin-top: 0; }
        .followups h2 { border-color: #f59e0b; color: #b45309; }
        .footer { position: fixed; right: 0; bottom: -19px; left: 0; color: #94a3b8; text-align: center; font-size: 7px; }
    </style>
</head>
<body>
    @php
        $logoPath = public_path('invoice-logo.png');
        if (! file_exists($logoPath)) $logoPath = public_path('logo.png');
        $businessInfoHtml = \App\Models\SiteOption::valueToHtml('document.business-info');
    @endphp
    <table class="header">
        <tr>
            <td class="logo-wrap">@if(file_exists($logoPath))<img class="logo" src="{{ $logoPath }}" alt="STEMMechanics">@endif</td>
            <td class="company">{!! $businessInfoHtml !!}</td>
            <td class="headline">hello.<br>this is your <span class="underline">fortnightly workplan</span>.</td>
        </tr>
    </table>
    <div class="period">{{ $workplan['weekStart']->format('D j M') }} to {{ $workplan['weekEnd']->format('D j M Y') }}</div>

    <table class="summary">
        <tr>
            <td class="sky"><strong>{{ $workplan['scheduledInvoices']->count() }}</strong><span>Invoice emails</span></td>
            <td class="pink"><strong>{{ $workplan['dueInvoices']->count() }}</strong><span>Invoices due</span></td>
            <td class="violet"><strong>{{ $workplan['workshops']->count() }}</strong><span>Workshops</span></td>
            <td class="emerald"><strong>{{ $workplan['reminders']->reject(fn ($reminder) => $reminder->isCompletedWorkshopTask())->count() }}</strong><span>Reminders</span></td>
            <td class="amber"><strong>{{ $workplan['quotes']->count() + $workplan['orders']->count() + $workplan['overdue']->count() }}</strong><span>Follow-ups</span></td>
        </tr>
    </table>

    <table class="content-grid">
        <tr>
            <td class="left-column">
    <div class="section coming">
        <h2>Coming up this fortnight</h2>
        <h3>Invoice emails scheduled</h3>
        <ul>@forelse($workplan['scheduledInvoices'] as $invoice)<li>{{ $invoice->invoice_number }} - Sends {{ $invoice->issue_date?->format('D j M') }} to {{ $invoice->user?->getName() ?: $invoice->billing_name }} ({{ money((float) $invoice->total_amount) }})</li>@empty<li>None scheduled.</li>@endforelse</ul>
        <h3>Invoices due for payment</h3>
        <ul>@forelse($workplan['dueInvoices'] as $invoice)<li>{{ $invoice->invoice_number }} - {{ $invoice->user?->getName() ?: $invoice->billing_name }}, due {{ $invoice->due_date?->format('D j M') }} ({{ money((float) $invoice->displayOutstandingAmount()) }} outstanding)</li>@empty<li>None due.</li>@endforelse</ul>
        <h3>Workshops</h3>
        <ul>@forelse($workplan['workshops'] as $workshop)<li>{{ $workshop->title }} - {{ $workshop->starts_at?->format('D j M, g:ia') }}{{ trim((string) $workshop->getLocationName()) !== '' ? ' - '.$workshop->getLocationName() : '' }}</li>@empty<li>No workshops.</li>@endforelse</ul>
        <h3>Tasks and reminders</h3>
        <ul>
            @forelse($workplan['reminders'] as $reminder)
                @php
                    $reminderWorkshop = $reminder->kind === \App\Services\ReminderService::WORKSHOP_TASK_KIND && $reminder->remindable instanceof \App\Models\Workshop
                        ? $reminder->remindable
                        : null;
                    $reminderTaskName = $reminderWorkshop
                        ? trim((string) str($reminder->subject)->after('Workshop task: ')->before(' — '))
                        : (string) $reminder->subject;
                    $reminderLocation = $reminderWorkshop ? trim((string) $reminderWorkshop->getLocationName()) : '';
                @endphp
                <li class="{{ $reminder->isCompletedWorkshopTask() ? 'completed' : '' }}">@if($reminderWorkshop){{ $reminderWorkshop->title }} · {{ $reminderTaskName }}@else{{ $reminderTaskName }}@endif — {{ $reminder->scheduled_at?->format('D j M, g:ia') }}{{ $reminderLocation !== '' ? ' · '.$reminderLocation : '' }}</li>
            @empty
                <li>No reminders.</li>
            @endforelse
        </ul>
    </div>
    <div class="section website">
        <h2>Website last fortnight</h2>
        <table class="website-stats">
            @foreach(array_chunk([
                'page_views' => 'Page views',
                'visitors' => 'Unique visitors',
                'store_views' => 'Store views',
                'workshop_views' => 'Workshop views',
            ], 2, true) as $statRow)
                <tr>
                    @foreach($statRow as $statKey => $statLabel)
                        <td><div class="website-stat"><span>{{ $statLabel }}</span><strong>{{ number_format($workplan['stats'][$statKey]) }}</strong><small class="{{ $workplan['websiteChanges'][$statKey]['direction'] }}">{{ $workplan['websiteChanges'][$statKey]['label'] }}</small></div></td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
            </td>
            <td class="right-column">
    <div class="section newsletter">
        <h2>Next newsletter</h2>
        <p><strong>Subject:</strong> {{ $workplan['newsletter']['subject'] }}</p>
        <ul class="muted"><li>Sends {{ $workplan['newsletter']['sendAt']->format('D j M, g:ia') }} - {{ $workplan['newsletter']['heading'] }}</li></ul>
        @foreach($workplan['newsletter']['contentSections'] as $newsletterSection)
            <h3>{{ $newsletterSection['title'] }}</h3>
            <ul>@forelse($newsletterSection['items'] as $item)<li>{{ $item->title }}@if($newsletterSection['type'] === 'workshops') - {{ $item->starts_at?->format('D j M, g:ia') }}{{ trim((string) $item->getLocationName()) !== '' ? ' - '.$item->getLocationName() : '' }}@endif</li>@empty<li>No items selected.</li>@endforelse</ul>
        @endforeach
    </div>

    <div class="section followups">
        <h2>Suggested follow-ups</h2>
        @if($workplan['quotes']->isEmpty() && $workplan['orders']->isEmpty() && $workplan['overdue']->isEmpty() && $workplan['pendingTransfers']->isEmpty() && $workplan['interests']->isEmpty() && $workplan['enquiries']->isEmpty())
        <p class="muted">No follow-ups are currently suggested.</p>
        @else
        <h3>Quotes</h3><ul>@forelse($workplan['quotes'] as $quote)<li>{{ $quote->quote_number }} - {{ $quote->user?->getName() }} - {{ money((float) $quote->total_amount) }}</li>@empty<li>None.</li>@endforelse</ul>
        <h3>Orders</h3><ul>@forelse($workplan['orders'] as $order)<li>{{ $order->order_number }} - {{ $order->user?->getName() ?: $order->billing_name }} - {{ money((float) $order->total_amount) }}</li>@empty<li>None.</li>@endforelse</ul>
        <h3>Overdue invoices</h3><ul>@forelse($workplan['overdue'] as $invoice)<li>{{ $invoice->invoice_number }} - {{ $invoice->user?->getName() ?: $invoice->billing_name }} - {{ money((float) $invoice->displayOutstandingAmount()) }} outstanding</li>@empty<li>None.</li>@endforelse</ul>
        <h3>Other</h3><ul>
            @foreach($workplan['pendingTransfers'] as $payment)<li>Pending transfer - {{ $payment->user?->getName() ?: 'Unknown customer' }} - {{ money((float) $payment->total_amount) }}</li>@endforeach
            @foreach($workplan['interests'] as $interest)<li>Workshop interest - {{ $interest->name }} - {{ $interest->workshop?->title }}</li>@endforeach
            @foreach($workplan['enquiries'] as $enquiry)<li>Website enquiry - {{ $enquiry->name }} - {{ $enquiry->subject }}</li>@endforeach
            @if($workplan['pendingTransfers']->isEmpty() && $workplan['interests']->isEmpty() && $workplan['enquiries']->isEmpty())<li>None.</li>@endif
        </ul>
        @endif
    </div>
            </td>
        </tr>
    </table>

    <div class="footer">Generated {{ now()->format('j M Y, g:ia') }} - STEMMechanics</div>
</body>
</html>
