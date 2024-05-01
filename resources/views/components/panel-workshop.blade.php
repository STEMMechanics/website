@props(['workshop'])

@php
    $statusClass = $workshop->status;
    $statusTitle = $workshop->status;

    if($workshop->status === 'scheduled') {
        $statusClass = 'soon';
        $statusTitle = 'Open soon';
    }
@endphp

<a href="{{ route('workshop.show', $workshop) }}" class="flex flex-col bg-white border rounded-lg overflow-hidden hover:shadow-lg hover:scale-[101%] transition-all relative {{ $attributes->get('class') }}">
    <div class="shadow border rounded px-3 py-2 absolute top-2 left-2 flex flex-col justify-center items-center bg-white">
        <div class="text-gray-600 font-bold leading-none">{{ $workshop->starts_at->format('j') }}</div>
        <div class="text-gray-600 text-xs uppercase">{{ $workshop->starts_at->format('M') }}</div>
    </div>
    <div class="border border-white absolute flex items-center justify-center top-5 -right-9 bg-gray-500 w-36 text-sm text-white font-bold uppercase py-1 rotate-45 h-8 sm-banner-{{ strtolower($statusClass) }}">{{ $statusTitle }}</div>
    <img src="{{ $workshop->hero?->url }}?md" alt="{{ $workshop->title }}" class="w-full h-64 object-cover object-center">
    <div class="flex-grow p-4 flex flex-col">
        <h2 class="flex-grow {{ strlen($workshop->title) > 25 ? 'text-lg' : 'text-xl' }} font-bold mb-2">{{ $workshop->title }}</h2>
        <div class="text-gray-600 text-sm mb-1 flex gap-2">
            <div class="w-6 flex items-center justify-center">
                <i class="fa-regular fa-calendar"></i>
            </div>{{ $workshop->starts_at->format('j/m/Y @ g:i a') }}
        </div>
        <div class="text-gray-600 text-sm mb-1 flex gap-2">
            <div class="w-6 flex items-center justify-center">
                <i class="fa-solid fa-location-dot"></i>
            </div>{{ $workshop->location->name }}
        </div>
        @if($workshop->ages)
            <div class="text-gray-600 text-sm mb-1 flex gap-2">
                <div class="w-6 flex items-center justify-center">
                    <i class="fa-regular fa-face-smile"></i>
                </div>{{ isset($workshop->ages) && $workshop->ages !== '' ? 'Ages ' . $workshop->ages : 'All ages' }}
            </div>
        @endif
        <div class="text-gray-600 text-sm mb-1 flex gap-2">
            <div class="w-6 flex items-center justify-center">
                <i class="fa-solid fa-dollar-sign"></i>
            </div>{{ isset($workshop->price) && $workshop->price !== '' && $workshop->price !== '0' ? $workshop->price : 'Free' }}
        </div>
    </div>
</a>
