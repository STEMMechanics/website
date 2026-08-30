@component('mail::message')
# Invoice scheduled for tomorrow

Invoice **{{ $invoice->invoice_number }}** for **{{ $invoice->user?->getName() ?: $invoice->billing_name ?: 'Unknown customer' }}** is scheduled to be issued and emailed at 8:00 am tomorrow.

**Recipient:** {{ $invoice->billing_email ?: $invoice->user?->email ?: 'Missing email address' }}<br>
**Total:** {{ money((float) $invoice->total_amount) }}<br>
**Issue date:** {{ $invoice->issue_date?->format('D j M Y') }}<br>
**Due date:** {{ $invoice->due_date?->format('D j M Y') }}

@component('mail::button', ['url' => route('admin.invoice.edit', $invoice)])
Review or cancel scheduled sending
@endcomponent

Untick “Schedule this draft” and save the invoice to cancel automatic sending.
@endcomponent
