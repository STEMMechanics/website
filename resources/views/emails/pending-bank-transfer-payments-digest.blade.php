@component('mail::message')
# Pending Bank Transfers

{{ count($payments) }} bank transfer {{ \Illuminate\Support\Str::plural('payment', count($payments)) }} {{ count($payments) === 1 ? 'is' : 'are' }} older than two days and still waiting to be cleared.

@foreach($payments as $payment)
### Payment #{{ $payment['id'] }}

- **Customer:** {{ $payment['customer_name'] }} @if($payment['customer_email'] !== '-') ({{ $payment['customer_email'] }})@endif
- **Received:** {{ $payment['received_on'] }} ({{ $payment['age_label'] }})
- **Amount:** {{ $payment['amount'] }}
@if(!empty($payment['reference']))
- **Reference:** {{ $payment['reference'] }}
@endif
@if(!empty($payment['allocations']))
- **Allocated invoices:** Invoice #{{ implode(', Invoice #', $payment['allocations']) }}
@endif
@if(!empty($payment['notes']))
- **Notes:** {{ $payment['notes'] }}
@endif

@component('mail::button', ['url' => $payment['edit_url']])
Open Payment #{{ $payment['id'] }}
@endcomponent

@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
