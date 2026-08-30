@component('mail::message')
<div style="margin:0 0 18px; color:#0f172a; font-size:32px; line-height:1.15; font-weight:800;">Weekly Workplan</div>

Your plan for **{{ $workplan['weekStart']->format('D j M') }}–{{ $workplan['weekEnd']->format('D j M Y') }}**.

<div style="margin:28px 0 14px; padding-bottom:8px; border-bottom:2px solid #0ea5e9; color:#0f172a; font-size:24px; line-height:1.2; font-weight:800;">Scheduled this week</div>

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Invoices to be sent ({{ $workplan['scheduledInvoices']->count() }})</div>

@forelse($workplan['scheduledInvoices'] as $invoice)
- **{{ $invoice->issue_date->format('D j M') }}:** [{{ $invoice->invoice_number }} – {{ $invoice->user?->getName() ?: $invoice->billing_name }}]({{ route('admin.invoice.edit', $invoice) }}) ({{ money((float) $invoice->total_amount) }})
@empty
- None scheduled.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Invoices due for payment ({{ $workplan['dueInvoices']->count() }})</div>

@forelse($workplan['dueInvoices'] as $invoice)
- **Due {{ $invoice->due_date->format('D j M') }}:** [{{ $invoice->invoice_number }} – {{ $invoice->user?->getName() ?: $invoice->billing_name }}]({{ route('admin.invoice.edit', $invoice) }}) ({{ money((float) $invoice->displayOutstandingAmount()) }} outstanding)
@empty
- None due this week.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Workshops ({{ $workplan['workshops']->count() }})</div>

@forelse($workplan['workshops'] as $workshop)
- **{{ $workshop->starts_at?->format('D j M, g:ia') }}:** [{{ $workshop->title }}]({{ route('admin.workshop.edit', $workshop) }}) · {{ $workshop->getLocationName() ?: 'Location not set' }}
@empty
- No workshops this week.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Workshop tasks and reminders ({{ $workplan['reminders']->count() }})</div>

@forelse($workplan['reminders'] as $reminder)
@php($reminderTaskName = (string) str($reminder->subject)->after('Workshop task: ')->before(' — '))
@php($reminderWorkshopName = $reminder->remindable instanceof \App\Models\Workshop ? $reminder->remindable->title : '')
@php($reminderWorkshopLocation = $reminder->remindable instanceof \App\Models\Workshop ? trim((string) $reminder->remindable->getLocationName()) : '')
- **{{ $reminder->scheduled_at->format('D j M, g:ia') }}:** [{{ $reminderWorkshopName !== '' ? $reminderWorkshopName.' · '.$reminderTaskName : $reminder->subject }}]({{ $reminder->action_url }}){{ $reminderWorkshopLocation !== '' ? ' · '.$reminderWorkshopLocation : '' }}
@empty
- No reminders scheduled.
@endforelse

<div style="margin:26px 0 12px; padding-bottom:7px; border-bottom:2px solid #8b5cf6; color:#6d28d9; font-size:22px; line-height:1.2; font-weight:800;">Next newsletter</div>

Scheduled for **{{ $workplan['newsletter']['sendAt']->format('D j M, g:ia') }}**.

- **Subject:** {{ $workplan['newsletter']['subject'] }}
- **Heading:** {{ $workplan['newsletter']['heading'] }}
- **Introduction:** {{ $workplan['newsletter']['introduction'] }}

**Workshops ({{ $workplan['newsletter']['workshops']->count() }})**
@forelse($workplan['newsletter']['workshops'] as $workshop)
- [{{ $workshop->title }}]({{ route('admin.workshop.edit', $workshop) }}) — {{ $workshop->starts_at?->format('D j M, g:ia') }} · {{ $workshop->getLocationName() ?: 'Location not set' }}
@empty
- No workshops are currently included.
@endforelse

**Store sections ({{ $workplan['newsletter']['storeSections']->count() }})**
@forelse($workplan['newsletter']['storeSections'] as $section)
- **{{ $section['title'] }}:** {{ collect($section['products'] ?? [])->pluck('title')->join(', ') ?: 'No products selected' }}
@empty
- No store sections are currently included.
@endforelse

[Review or change the newsletter]({{ route('admin.subscription.index') }}) before it is sent. Its subject, heading and content order remain locked unless an administrator changes them.

<div style="margin:30px 0 14px; padding-bottom:8px; border-bottom:2px solid #f59e0b; color:#0f172a; font-size:24px; line-height:1.2; font-weight:800;">Follow up</div>

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Open quotes ({{ $workplan['quotes']->count() }})</div>

@forelse($workplan['quotes'] as $quote)
- [{{ $quote->quote_number }} – {{ $quote->user?->getName() }}]({{ route('admin.quote.edit', $quote) }}), {{ money((float) $quote->total_amount) }}, last updated {{ $quote->updated_at->diffForHumans() }}
@empty
- None needing follow-up.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Unpaid or quote-request orders ({{ $workplan['orders']->count() }})</div>

@forelse($workplan['orders'] as $order)
- [{{ $order->order_number }} – {{ $order->user?->getName() ?: $order->billing_name }}]({{ route('admin.shop.order.edit', $order) }}), {{ money((float) $order->total_amount) }}
@empty
- None needing follow-up.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Workshop interest without a booking ({{ $workplan['interests']->count() }})</div>

@forelse($workplan['interests'] as $interest)
- {{ $interest->name }} ({{ $interest->email }}) – {{ $interest->workshop?->title }} · {{ $interest->workshop?->getLocationName() ?: 'Location not set' }}
@empty
- None recorded in the last 30 days.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Recent website enquiries ({{ $workplan['enquiries']->count() }})</div>

@forelse($workplan['enquiries'] as $enquiry)
- [{{ $enquiry->name }} – {{ $enquiry->subject }}](mailto:{{ $enquiry->email }}), received {{ $enquiry->created_at->diffForHumans() }}
@empty
- No website enquiries in the last 30 days.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Overdue invoices ({{ $workplan['overdue']->count() }})</div>

@forelse($workplan['overdue'] as $invoice)
- [{{ $invoice->invoice_number }} – {{ $invoice->user?->getName() ?: $invoice->billing_name }}]({{ route('admin.invoice.edit', $invoice) }}), {{ money((float) $invoice->displayOutstandingAmount()) }}, due {{ $invoice->due_date?->format('j M') }}
@empty
- None overdue.
@endforelse

<div style="margin:22px 0 10px; color:#334155; font-size:18px; line-height:1.3; font-weight:800;">Pending bank transfers ({{ $workplan['pendingTransfers']->count() }})</div>

@forelse($workplan['pendingTransfers'] as $payment)
- [{{ $payment->user?->getName() ?: 'Unknown customer' }}]({{ route('admin.payment.edit', $payment) }}), {{ money((float) $payment->total_amount) }}, received {{ $payment->received_on?->diffForHumans() }}
@empty
- None awaiting confirmation.
@endforelse

<div style="margin:30px 0 14px; padding-bottom:8px; border-bottom:2px solid #10b981; color:#0f172a; font-size:24px; line-height:1.2; font-weight:800;">Last week at a glance</div>

- Page views: **{{ number_format($workplan['stats']['page_views']) }}** ({{ $workplan['websiteChanges']['page_views']['label'] }})
- Unique visitors: **{{ number_format($workplan['stats']['visitors']) }}** ({{ $workplan['websiteChanges']['visitors']['label'] }})
- Store and product views: **{{ number_format($workplan['stats']['store_views']) }}** ({{ $workplan['websiteChanges']['store_views']['label'] }})
- Workshop views: **{{ number_format($workplan['stats']['workshop_views']) }}** ({{ $workplan['websiteChanges']['workshop_views']['label'] }})
- Store orders: **{{ number_format($workplan['stats']['orders']) }}**
- Workshop tickets sold: **{{ number_format($workplan['stats']['tickets_sold']) }}**
- Income: **{{ money($workplan['stats']['income']) }}**
- Expenses: **{{ money($workplan['stats']['expenses']) }}**
- Refunds: **{{ money($workplan['stats']['refunds']) }}**

@component('mail::button', ['url' => route('admin.dashboard')])
Open admin dashboard
@endcomponent
@endcomponent
