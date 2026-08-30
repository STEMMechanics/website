@component('mail::message')
# Scheduled invoice was not sent

Invoice **{{ $invoice->invoice_number }}** could not be emailed and has been returned to draft so it can be retried safely.

**Error:** {{ $error }}

@component('mail::button', ['url' => route('admin.invoice.edit', $invoice)])
Review invoice
@endcomponent
@endcomponent
