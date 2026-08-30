@component('mail::message')
# Weekly Workplan

Your plan for **{{ $workplan['weekStart']->format('D j M') }}–{{ $workplan['weekEnd']->format('D j M Y') }}**.

## Scheduled this week

### Invoices to be sent ({{ $workplan['scheduledInvoices']->count() }})
@forelse($workplan['scheduledInvoices'] as $invoice)
- **{{ $invoice->issue_date->format('D j M') }}:** [{{ $invoice->invoice_number }} – {{ $invoice->user?->getName() ?: $invoice->billing_name }}]({{ route('admin.invoice.edit', $invoice) }}) ({{ money((float) $invoice->total_amount) }})
@empty
- None scheduled.
@endforelse

### Invoices due for payment ({{ $workplan['dueInvoices']->count() }})
@forelse($workplan['dueInvoices'] as $invoice)
- **Due {{ $invoice->due_date->format('D j M') }}:** [{{ $invoice->invoice_number }} – {{ $invoice->user?->getName() ?: $invoice->billing_name }}]({{ route('admin.invoice.edit', $invoice) }}) ({{ money((float) $invoice->displayOutstandingAmount()) }} outstanding)
@empty
- None due this week.
@endforelse

### Workshops ({{ $workplan['workshops']->count() }})
@forelse($workplan['workshops'] as $workshop)
- **{{ $workshop->starts_at?->format('D j M, g:ia') }}:** [{{ $workshop->title }}]({{ route('admin.workshop.edit', $workshop) }})
@empty
- No workshops this week.
@endforelse

### Workshop tasks and reminders ({{ $workplan['reminders']->count() }})
@forelse($workplan['reminders'] as $reminder)
@php($reminderTaskName = (string) str($reminder->subject)->after('Workshop task: ')->before(' — '))
@php($reminderWorkshopName = $reminder->remindable instanceof \App\Models\Workshop ? $reminder->remindable->title : '')
- **{{ $reminder->scheduled_at->format('D j M, g:ia') }}:** [{{ $reminderWorkshopName !== '' ? $reminderWorkshopName.' · '.$reminderTaskName : $reminder->subject }}]({{ $reminder->action_url }})
@empty
- No reminders scheduled.
@endforelse

### Next newsletter

Scheduled for **{{ $workplan['newsletter']['sendAt']->format('D j M, g:ia') }}**.

- **Subject:** {{ $workplan['newsletter']['subject'] }}
- **Heading:** {{ $workplan['newsletter']['heading'] }}
- **Introduction:** {{ $workplan['newsletter']['introduction'] }}

**Workshops ({{ $workplan['newsletter']['workshops']->count() }})**
@forelse($workplan['newsletter']['workshops'] as $workshop)
- [{{ $workshop->title }}]({{ route('admin.workshop.edit', $workshop) }}) — {{ $workshop->starts_at?->format('D j M, g:ia') }}
@empty
- No workshops are currently included.
@endforelse

**Store sections ({{ $workplan['newsletter']['storeSections']->count() }})**
@forelse($workplan['newsletter']['storeSections'] as $section)
- **{{ $section['title'] }}:** {{ collect($section['products'] ?? [])->pluck('title')->join(', ') ?: 'No products selected' }}
@empty
- No store sections are currently included.
@endforelse

[Review or change the newsletter]({{ route('admin.subscription.index') }}) before it is sent. The subject and heading are selected when this preview is generated and may vary if the workplan is refreshed.

## Follow up

### Open quotes ({{ $workplan['quotes']->count() }})
@forelse($workplan['quotes'] as $quote)
- [{{ $quote->quote_number }} – {{ $quote->user?->getName() }}]({{ route('admin.quote.edit', $quote) }}), {{ money((float) $quote->total_amount) }}, last updated {{ $quote->updated_at->diffForHumans() }}
@empty
- None needing follow-up.
@endforelse

### Unpaid or quote-request orders ({{ $workplan['orders']->count() }})
@forelse($workplan['orders'] as $order)
- [{{ $order->order_number }} – {{ $order->user?->getName() ?: $order->billing_name }}]({{ route('admin.shop.order.edit', $order) }}), {{ money((float) $order->total_amount) }}
@empty
- None needing follow-up.
@endforelse

### Workshop interest without a booking ({{ $workplan['interests']->count() }})
@forelse($workplan['interests'] as $interest)
- {{ $interest->name }} ({{ $interest->email }}) – {{ $interest->workshop?->title }}
@empty
- None recorded in the last 30 days.
@endforelse

### Recent website enquiries ({{ $workplan['enquiries']->count() }})
@forelse($workplan['enquiries'] as $enquiry)
- [{{ $enquiry->name }} – {{ $enquiry->subject }}](mailto:{{ $enquiry->email }}), received {{ $enquiry->created_at->diffForHumans() }}
@empty
- No website enquiries in the last 30 days.
@endforelse

### Overdue invoices ({{ $workplan['overdue']->count() }})
@forelse($workplan['overdue'] as $invoice)
- [{{ $invoice->invoice_number }} – {{ $invoice->user?->getName() ?: $invoice->billing_name }}]({{ route('admin.invoice.edit', $invoice) }}), {{ money((float) $invoice->displayOutstandingAmount()) }}, due {{ $invoice->due_date?->format('j M') }}
@empty
- None overdue.
@endforelse

### Pending bank transfers ({{ $workplan['pendingTransfers']->count() }})
@forelse($workplan['pendingTransfers'] as $payment)
- [{{ $payment->user?->getName() ?: 'Unknown customer' }}]({{ route('admin.payment.edit', $payment) }}), {{ money((float) $payment->total_amount) }}, received {{ $payment->received_on?->diffForHumans() }}
@empty
- None awaiting confirmation.
@endforelse

## Last week at a glance

- Page views: **{{ number_format($workplan['stats']['page_views']) }}**
- Unique visitors: **{{ number_format($workplan['stats']['visitors']) }}**
- Store and product views: **{{ number_format($workplan['stats']['store_views']) }}**
- Workshop views: **{{ number_format($workplan['stats']['workshop_views']) }}**
- Store orders: **{{ number_format($workplan['stats']['orders']) }}**
- Workshop tickets sold: **{{ number_format($workplan['stats']['tickets_sold']) }}**
- Income: **{{ money($workplan['stats']['income']) }}**
- Expenses: **{{ money($workplan['stats']['expenses']) }}**
- Refunds: **{{ money($workplan['stats']['refunds']) }}**

@component('mail::button', ['url' => route('admin.dashboard')])
Open admin dashboard
@endcomponent
@endcomponent
