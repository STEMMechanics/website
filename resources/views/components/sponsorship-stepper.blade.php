@props(['current' => 1])
@php($steps = ['Your details', 'Sponsorship', 'Payment'])
<nav aria-label="Sponsorship checkout progress" class="mb-5">
    <ol class="grid grid-cols-3 gap-2">
        @foreach($steps as $index => $label)
            @php($number = $index + 1)
            <li class="min-w-0">
                <span class="block h-1.5 rounded-full {{ $number <= $current ? 'bg-primary-color' : 'bg-gray-200' }}"></span>
                <span class="mt-2 block truncate text-xs {{ $number === $current ? 'font-semibold text-gray-900' : 'text-gray-500' }}">{{ $number }}. {{ $label }}</span>
            </li>
        @endforeach
    </ol>
</nav>
