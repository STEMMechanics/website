@props(['event', 'details' => false])
@php($outcome = $event->paymentOutcome())
@if($outcome)
    <div {{ $attributes->class(['mt-2 space-y-1']) }}>
        <x-ui.badge :tone="$outcome['tone']">{{ $outcome['label'] }}</x-ui.badge>
        @if($outcome['cvv_rejected'])
            <p class="text-xs font-medium text-red-700">CVV rejected</p>
        @endif
        @if($details)
        @foreach($outcome['errors'] as $error)
            <p class="text-xs text-gray-600 break-words">
                {{ \Illuminate\Support\Str::headline(strtolower($error['code'])) }}
                @if($details && $error['detail'] !== '')<span class="block">{{ $error['detail'] }}</span>@endif
            </p>
        @endforeach
        @endif
    </div>
@endif
