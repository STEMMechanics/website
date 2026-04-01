@props(['workshop'])

@php
    $statusClass = $workshop->publicStatus();
    $statusTitle = $workshop->publicStatusLabel();
    $isAdmin = (bool) (auth()->user()?->isAdmin() ?? false);
    $showHostedFor = $workshop->is_private && !empty($workshop->hosted_for);
    $locationLabel = $showHostedFor
        ? $workshop->hosted_for
        : ($workshop->is_private ? 'Private Location' : $workshop->getLocationName());
    $locationIcon = $showHostedFor ? 'fa-solid fa-building' : 'fa-solid fa-location-dot';
    $cardStartLabel = $workshop->courseScheduleFirstStartLabel();
    $cardCadenceLabel = $workshop->courseScheduleCadenceLabel();

    if($workshop->status === 'scheduled') {
        $statusClass = 'soon';
        $statusTitle = 'Opens Soon';
    }
@endphp

<a href="{{ route('workshop.show', $workshop) }}" class="relative overflow-hidden p-0.5 hover:scale-[101%] transition-all">
    <div class="flex flex-col bg-white border border-gray-200 rounded-lg overflow-hidden hover:shadow-lg transition-all {{ $attributes->get('class') }}">
        <div class="shadow border border-gray-200 rounded px-3 py-2 absolute top-2 left-2 flex flex-col justify-center items-center bg-white {{ $cardStartLabel === 'Anytime' ? 'w-auto min-w-20' : 'w-14' }}">
            @if($cardStartLabel === 'Anytime')
                <div class="text-gray-600 text-sm font-bold leading-none whitespace-nowrap">Anytime</div>
            @else
                <div class="text-gray-600 font-bold leading-none">{{ $workshop->effectiveStartsAt()?->format('j') ?? $workshop->starts_at?->format('j') }}</div>
                <div class="text-gray-600 text-xs uppercase">{{ $workshop->effectiveStartsAt()?->format('M') ?? $workshop->starts_at?->format('M') }}</div>
            @endif
        </div>
        <div class="shadow-lg border border-white/50 absolute flex items-center justify-center top-5 -right-9 bg-gray-500 w-36 text-sm text-white font-bold uppercase py-1 rotate-45 h-8 sm-banner-{{ strtolower($statusClass) }}">{{ $statusTitle }}</div>
        <img src="{{ $workshop->hero?->url }}?md" alt="{{ $workshop->title }}" class="w-full h-64 object-cover object-center">
        <div class="grow p-4 flex flex-col">
            <h2 class="grow {{ strlen($workshop->title) > 25 ? 'text-lg' : 'text-xl' }} font-bold mb-2 whitespace-nowrap overflow-hidden text-ellipsis">{{ $workshop->title }}</h2>
            <div class="text-gray-600 text-sm mb-1 flex gap-2">
                <div class="w-6 flex items-center justify-center">
                    <i class="fa-regular fa-calendar"></i>
                </div>{{ $cardStartLabel }}
            </div>
            <div class="text-gray-600 text-sm mb-1 flex gap-2">
                <div class="w-6 flex items-center justify-center">
                    <i class="{{ $locationIcon }}"></i>
                </div>{{ $locationLabel }}@if($cardCadenceLabel) - {{ $cardCadenceLabel }}@endif
            </div>
            @if($workshop->ages)
                <div class="text-gray-600 text-sm mb-1 flex gap-2">
                    <div class="w-6 flex items-center justify-center">
                        <i class="fa-regular fa-face-smile"></i>
                    </div>{{ $workshop->ages ? 'Ages ' . $workshop->ages : 'All ages' }}
                </div>
            @endif
            <div class="text-gray-600 text-sm mb-1 flex gap-2">
                <div class="w-6 flex items-center justify-center">
                    <i class="fa-solid fa-dollar-sign"></i>
                </div>
                {{ $workshop->price && $workshop->price !== '0' ? number_format((float)$workshop->price, 2) : 'Free' }}
            </div>
        </div>
    </div>
</a>
