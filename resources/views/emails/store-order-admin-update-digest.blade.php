@component('mail::message')
# Store Order Update Digest

Here is the batched store order activity for {{ $digestDateLabel }}.

@foreach($orders as $order)
### Order {{ $order['order_number'] }}

- **Customer:** {{ $order['customer_name'] }} ({{ $order['customer_email'] }})
- **Current status:** {{ $order['status_label'] }}
@foreach($order['updates'] as $update)
- @if(!empty($update['time'])) **{{ $update['time'] }}** · @endif{{ $update['summary'] }}
@if(!empty($update['detail']))
  <br><span style="color:#4b5563;">{{ $update['detail'] }}</span>
@endif
@endforeach

@component('mail::button', ['url' => $order['admin_url']])
Open in Admin
@endcomponent

@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
