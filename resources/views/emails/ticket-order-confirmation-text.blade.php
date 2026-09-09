Booking confirmed

Hi {!! $confirmation['firstName'] !!},

Thank you for your booking. {!! $confirmation['attachments'] !!}

YOUR WORKSHOP
{!! $workshop['title'] ?? '-' !!}
@if(!empty($workshop['schedule']))
@foreach($workshop['schedule'] as $session)
{!! $session !!}
@endforeach
@else
{!! $workshop['time'] ?? $workshop['starts_at'] ?? '-' !!}
@endif
{!! $workshop['location'] ?? '-' !!}

@if(count($tickets) > 0)
{!! $confirmation['ticketHeading'] !!}
@foreach($tickets as $ticket)
{!! $ticket['name'] ?? '-' !!} — Ticket {!! $ticket['reference'] ?? '-' !!}{!! !empty($ticket['earlyBird']) ? ' · Early bird' : '' !!}
@endforeach
@endif

@if($invoice)
Invoice #{!! $invoice['number'] ?? '-' !!} · {!! ucfirst($invoice['status'] ?? '-') !!}
@endif
@if(!$confirmation['free'])
Payment method: {!! $paymentMethodLabel !!}
@if($creditAppliedAmount > 0.0001)
Account credit applied: ${!! number_format($creditAppliedAmount, 2) !!}
@if($paymentAmount > 0.0001)
Payment received: ${!! number_format($paymentAmount, 2) !!}
@endif
@endif
@endif
{!! $confirmation['free'] ? 'No payment required' : ($confirmation['settled'] ? 'Total paid' : 'Amount due') !!}: ${!! number_format($confirmation['free'] ? 0 : ($confirmation['settled'] ? $amount : $confirmation['amountDue']), 2) !!}

@if($equipmentOrder)
Store Order #{!! $equipmentOrder['number'] !!}
{!! $equipmentOrder['pickup'] ? 'Collection' : $equipmentOrder['delivery'] !!}
@foreach($confirmation['delivery'] as $deliveryLine)
{!! $deliveryLine !!}
@endforeach
@if($equipmentOrder['pickup'])
We’ll let you know when your order is ready to collect.
@endif

View Store Order: {!! $equipmentOrder['url'] !!}
@endif

@if(trim((string) ($workshop['participantInformation'] ?? '')) !== '')
Before your workshop
{!! html_entity_decode(strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />', '</li>'], "\n", (string) $workshop['participantInformation'])), ENT_QUOTES | ENT_HTML5, 'UTF-8') !!}
@endif

Manage {!! $ticketCount === 1 ? 'ticket' : 'tickets' !!}: {!! url('/tickets') !!}

We look forward to seeing you.
The STEMMechanics team

STEMMechanics · 63 Dalton Street, Westcourt QLD 4870
{!! route('index') !!}
Privacy: {!! route('privacy') !!}
Terms: {!! route('terms-conditions') !!}
