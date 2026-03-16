@component('mail::message')
Hi {{ trim((string) strtok($recipientName, ' ')) ?: $recipientName }},

Here is your order update summary for {{ $digestDateLabel }}.

@foreach($orders as $order)
### Order {{ $order['order_number'] }}

- **Current status:** {{ $order['status_label'] }}
@foreach($order['updates'] as $update)
- @if(!empty($update['time'])) **{{ $update['time'] }}** · @endif{{ $update['summary'] }}
@if(!empty($update['detail']))
  <br><span style="color:#4b5563;">{{ $update['detail'] }}</span>
@endif
@endforeach

<p class="tall center">
  @component('mail::button', ['url' => $order['order_url']])
  View Order
  @endcomponent
</p>

@endforeach

Thanks,<br>
{{ config('app.name') }}
@endcomponent
