@php
    $description = 'Discover upcoming STEMMechanics workshops in '.$suburb.', with hands-on STEM activities for children, families, schools and community groups.';
    $eventListJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'ItemList',
        'name' => 'STEM Workshops in '.$suburb,
        'itemListElement' => $workshops->values()->map(fn ($workshop, $index) => [
            '@type' => 'ListItem',
            'position' => $index + 1,
            'url' => route('workshop.show', $workshop),
            'item' => [
                '@type' => 'Event',
                'name' => (string) $workshop->title,
                'startDate' => $workshop->effectiveStartsAt()?->toIso8601String(),
                'endDate' => $workshop->effectiveEndsAt()?->toIso8601String(),
                'location' => [
                    '@type' => 'Place',
                    'name' => $workshop->getLocationName(),
                    'address' => (string) ($workshop->location?->address ?? ''),
                ],
            ],
        ])->all(),
    ];
@endphp
<x-layout
    :title="'STEM Workshops in '.$suburb"
    :description="$description"
    :canonical="route('workshop.suburb', Illuminate\Support\Str::slug($suburb))"
    :jsonLd="$eventListJsonLd"
>
    <x-mast>STEM Workshops in {{ $suburb }}</x-mast>
    <x-container class="py-8">
        <div class="mx-auto max-w-3xl text-center">
            <p class="text-lg leading-8 text-gray-700">Explore upcoming hands-on STEM workshops in {{ $suburb }}. Sessions are designed to make science, technology, engineering and mechanics approachable, practical and fun.</p>
        </div>

        @if($workshops->isNotEmpty())
            <div class="mt-8 grid grid-cols-1 gap-5 md:grid-cols-2 lg:grid-cols-3">
                @foreach($workshops as $workshop)
                    <x-panel-workshop :workshop="$workshop" />
                @endforeach
            </div>
        @else
            <div class="mx-auto mt-8 max-w-2xl rounded-lg border border-gray-200 bg-white p-8 text-center">
                <h2 class="text-xl font-bold">No upcoming {{ $suburb }} workshops are listed yet</h2>
                <p class="mt-2 text-gray-600">Browse the full workshop calendar for nearby and online sessions.</p>
                <x-ui.button class="mt-5" href="{{ route('workshop.index') }}">View all workshops</x-ui.button>
            </div>
        @endif
        @if($nearbySuburbs->isNotEmpty())
            <nav class="mt-10 border-t border-gray-200 pt-6" aria-label="Nearby workshop areas">
                <h2 class="text-lg font-bold">Explore nearby areas</h2>
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach($nearbySuburbs as $nearbySuburb)
                        <a class="rounded-full border border-gray-300 bg-white px-3 py-1.5 text-sm font-semibold text-gray-700 transition hover:border-primary-color hover:text-primary-color" href="{{ route('workshop.suburb', Illuminate\Support\Str::slug($nearbySuburb)) }}">{{ $nearbySuburb }}</a>
                    @endforeach
                </div>
            </nav>
        @endif
    </x-container>
</x-layout>
