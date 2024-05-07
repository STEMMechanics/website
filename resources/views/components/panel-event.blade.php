@props(['event'])

@php
    $statusClass = $event->status;
    $statusTitle = $event->status;

    if($event->status === 'scheduled') {
        $statusClass = 'soon';
        $statusTitle = 'Open soon';
    }
@endphp

<a href="{{ route('event.show', $event) }}" class="flex flex-col bg-white border rounded-lg overflow-hidden hover:shadow-lg hover:scale-[101%] transition-all relative {{ $attributes->get('class') }}">
    <div class="shadow border rounded px-3 py-2 absolute top-2 left-2 flex flex-col justify-center items-center bg-white">
        <div class="text-gray-600 font-bold leading-none">{{ $event->starts_at->format('j') }}</div>
        <div class="text-gray-600 text-xs uppercase">{{ $event->starts_at->format('M') }}</div>
    </div>
    <div class="border border-white border-opacity-50 absolute flex items-center justify-center top-5 -right-9 bg-gray-500 w-36 text-sm text-white font-bold uppercase py-1 rotate-45 h-8 sm-banner-{{ strtolower($statusClass) }}">{{ $statusTitle }}</div>
    <img src="{{ $event->hero?->url }}?md" alt="{{ $event->title }}" class="w-full h-64 object-cover object-center">
    <div class="flex-grow p-4 flex flex-col">
        <h2 class="flex-grow {{ strlen($event->title) > 25 ? 'text-lg' : 'text-xl' }} font-bold mb-2">{{ $event->title }}</h2>
        <div class="text-gray-600 text-sm mb-1 flex gap-2">
            <div class="w-6 flex items-center justify-center">
                <i class="fa-regular fa-calendar"></i>
            </div>{{ $event->starts_at->format('j/m/Y @ g:i a') }}
        </div>
        <div class="text-gray-600 text-sm mb-1 flex gap-2">
            <div class="w-6 flex items-center justify-center">
                <i class="fa-solid fa-location-dot"></i>
            </div>{{ $event->location->name }}
        </div>
        @if($event->ages)
            <div class="text-gray-600 text-sm mb-1 flex gap-2">
                <div class="w-6 flex items-center justify-center">
                    <i class="fa-regular fa-face-smile"></i>
                </div>{{ isset($event->ages) && $event->ages !== '' ? 'Ages ' . $event->ages : 'All ages' }}
            </div>
        @endif
        <div class="text-gray-600 text-sm mb-1 flex gap-2">
            <div class="w-6 flex items-center justify-center">
                <i class="fa-solid fa-dollar-sign"></i>
            </div>{{ isset($event->price) && $event->price !== '' && $event->price !== '0' ? $event->price : 'Free' }}
        </div>
    </div>
</a>
