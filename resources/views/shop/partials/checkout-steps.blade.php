@php
    $steps = [
        'details' => ['number' => '1', 'label' => 'Details'],
        'payment' => ['number' => '2', 'label' => 'Payment'],
    ];
@endphp

<div class="mb-6 flex flex-wrap gap-3">
    @foreach($steps as $key => $step)
        @php
            $isCurrent = $current === $key;
            $isComplete = $current === 'payment' && $key === 'details';
        @endphp
        <x-ui.badge :color="$isCurrent ? 'primary' : ($isComplete ? 'success' : 'gray')" :variant="$isCurrent ? 'solid' : ($isComplete ? 'soft' : 'outline')" class="gap-3 font-medium">
            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full {{ $isCurrent ? 'bg-white/20 text-white' : ($isComplete ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600') }}">
                {{ $isComplete ? '✓' : $step['number'] }}
            </span>
            <span>{{ $step['label'] }}</span>
        </x-ui.badge>
    @endforeach
</div>
