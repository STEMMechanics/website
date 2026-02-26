@php
    $seoDescription = \Illuminate\Support\Str::limit(trim(strip_tags((string) ($workshop->content ?? ''))), 160, '...');

    $eventLocation = $workshop->location_id
        ? [
            '@type' => 'Place',
            'name' => (string) $workshop->getLocationName(),
            'address' => (string) ($workshop->location?->address ?? ''),
        ]
        : [
            '@type' => 'VirtualLocation',
            'url' => route('workshop.show', $workshop),
        ];

    $eventStatus = match ((string) ($workshop->status ?? '')) {
        'cancelled' => 'https://schema.org/EventCancelled',
        'scheduled' => 'https://schema.org/EventScheduled',
        default => 'https://schema.org/EventScheduled',
    };

    $eventJsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Event',
        'name' => (string) ($workshop->title ?? 'Workshop'),
        'description' => $seoDescription,
        'startDate' => $workshop->starts_at?->toIso8601String(),
        'endDate' => $workshop->ends_at?->toIso8601String(),
        'eventStatus' => $eventStatus,
        'eventAttendanceMode' => $workshop->location_id
            ? 'https://schema.org/OfflineEventAttendanceMode'
            : 'https://schema.org/OnlineEventAttendanceMode',
        'location' => $eventLocation,
        'organizer' => [
            '@type' => 'Organization',
            'name' => 'STEMMechanics',
            'url' => url('/'),
        ],
        'url' => route('workshop.show', $workshop),
    ];

    $rawPrice = trim((string) ($workshop->price ?? ''));
    if (is_numeric($rawPrice)) {
        $eventJsonLd['offers'] = [
            '@type' => 'Offer',
            'priceCurrency' => 'AUD',
            'price' => number_format((float) $rawPrice, 2, '.', ''),
            'availability' => $workshop->status === 'full'
                ? 'https://schema.org/SoldOut'
                : 'https://schema.org/InStock',
            'url' => route('workshop.show', $workshop),
        ];
    }
@endphp

<x-layout
    :title="$workshop->title"
    :description="$seoDescription"
    :canonical="route('workshop.show', $workshop)"
    :noindex="$workshop->status === 'hidden'"
    :jsonLd="$eventJsonLd"
>
    <x-container>
        <x-ui.image-hero :image="$workshop->hero?->url" class="my-8" />
        <div class="flex sm:gap-16 gap-4 flex-col sm:flex-row">
            <div class="flex flex-col flex-1">
                <h1 class="text-3xl font-bold mb-6">{!! $workshop->title !!}</h1>
                <article class="content mb-4">{!! $workshop->content !!}</article>
                <x-ui.filelist class="mt-16" value="{!! $workshop->files()->orderBy('name')->get() !!}" />
            </div>
            <div class="flex flex-col sm:pt-8 basis-64 flex-grow-0 flex-shrink-0">
                @if($workshop->status === 'closed')
                    <div class="sm-registration-closed">Registration for this event has closed.</div>
                @elseif($workshop->status === 'full')
                    <div class="sm-registration-full">This workshop is currently full.</div>
                @elseif($workshop->status === 'scheduled')
                    <div class="sm-registration-scheduled">Registration for this<br>workshop opens soon.</div>
                @elseif($workshop->status === 'cancelled')
                    <div class="sm-registration-cancelled">This workshop has been cancelled.</div>
                @elseif($workshop->registration === 'none')
                    <div class="sm-registration-none">Registration not required for this event. Arrive early to avoid disappointment as seating maybe limited.</div>
                @elseif($workshop->isPrivate())
                    <div class="sm-registration-private">This workshop is a private event and is not open to public registration.</div>
                @endif
                @if($workshop->registration === 'tickets')
                @include('workshop.partials.ticket-cta', [
                    'workshop' => $workshop,
                    'canGetTickets' => $canGetTickets,
                    'availableTickets' => $availableTickets,
                    'privateCodeRequired' => $workshop->requiresPrivateTicketCode(),
                ])
                @elseif($workshop->registration === 'link')
                    @if($workshop->isPrivate() && !($privateLockedNoCode ?? false))
                        <div x-data="{ privateAccessModalOpen: {{ $errors->has('private_code') ? 'true' : 'false' }} }" class="flex flex-col mb-4">
                            <x-ui.button type="button" x-on:click="privateAccessModalOpen = true">Register for Event</x-ui.button>

                            <div
                                    x-show="privateAccessModalOpen"
                                    x-cloak
                                    class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                    x-on:keydown.escape.window="privateAccessModalOpen = false"
                            >
                                <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="privateAccessModalOpen = false"></div>
                                <div class="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200 p-6">
                                    <h3 class="text-lg font-bold text-gray-900">Private Event Registration</h3>
                                    <p class="mt-2 text-sm text-gray-700">
                                        This is a private event. Enter your access code to register.<br>If you don’t have a code, contact the organiser.
                                    </p>

                                    <form method="POST" action="{{ route('workshop.private-access', $workshop) }}" class="mt-6">
                                        @csrf
                                        <x-ui.input name="private_code" label="Access Code" value="{{ old('private_code') }}" required autofocus />
                                        <div class="pt-2 flex justify-end gap-3">
                                            <x-ui.button type="button" color="primary-outline" x-on:click="privateAccessModalOpen = false">Cancel</x-ui.button>
                                            <x-ui.button type="submit">Unlock Registration</x-ui.button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    @elseif(!$workshop->isPrivate())
                        <x-ui.button href="{{ $workshop->registration_data }}" class="my-4">Register for Event</x-ui.button>
                    @endif
                @elseif($workshop->registration === 'email')
                    <div class="sm-registration-email">Registration for this event by emailing <a href="mailto:{{ $workshop->registration_data }}" class="link">{{ $workshop->registration_data }}</a>.</div>
                @elseif($workshop->registration === 'message')
                    <div class="sm-registration-message">{{ $workshop->registration_data }}</div>
                @endif
                @if(auth()->user()?->isAdmin())
                    <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                    @if($workshop->pick_list_template_id)
                        <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.pick-list', $workshop) }}">Pick List</x-ui.button>
                    @endif
                    @if($adminCanViewTickets ?? false)
                        <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.tickets', $workshop) }}">View Tickets</x-ui.button>
                    @endif
                    <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.attendance', $workshop) }}">Attendance</x-ui.button>
                @endif
                <h2 class="text-gray-600 text-lg font-bold mt-4 mb-2"><i class="mr-1 fa-regular fa-calendar"></i> Date/Time</h2>
                <p class="text-gray-600 text-sm pl-6 mb-6">{!! implode('<br />', \App\Helpers::createTimeDurationStr($workshop->starts_at, $workshop->ends_at)) !!}</p>
                @php($hostedFor = $workshop->hosted_for)
                @if(!empty($hostedFor))
                <h2 class="text-gray-600 text-lg font-bold mb-2">
                    <i class="mr-1 fa-solid fa-building"></i>
                    Hosted For
                </h2>
                <div class="text-gray-600 text-sm pl-6 mb-6">
                    <p>{{ $workshop->hosted_for }}</p>
                </div>
                @endif
                @if(!$workshop->isPrivate() || (bool) (auth()->user()?->isAdmin() ?? false))
                <h2 class="text-gray-600 text-lg font-bold mb-2">
                    <i class="mr-1 fa-solid fa-location-dot"></i>
                    Location
                </h2>
                <div class="text-gray-600 text-sm pl-6 mb-6">
                    @if($workshop->location?->url)
                        <a href="{{ $workshop->location->url }}" class="link">
                            @endif
                            <p>{{ $workshop->getLocationName() }}</p>
                            @if($workshop->location?->url)
                        </a>
                    @endif

                    @if($workshop->location?->address)
                        @if($workshop->location?->address_url)
                            <a href="{{ $workshop->location->address_url }}" class="link" target="_blank">
                                @endif
                                <p class="text-xs">{{ $workshop->location->address }}</p>
                                @if($workshop->location?->address_url)
                            </a>
                        @endif
                    @endif
                </div>
                @endif
                <h2 class="text-gray-600 text-lg font-bold mb-2"><i class="mr-1 fa-regular fa-face-smile"></i> {{ isset($workshop->ages) && $workshop->ages !== '' ? 'Ages ' . $workshop->ages : 'All ages' }}</h2>
                @if(\App\Helpers::isUnderAge($workshop->ages))
                    <p class="text-gray-600 text-xs pl-3 ml-2 mb-6 border-l-4 border-l-yellow-400">Parental supervision may be required for children 8 years of age and under.</p>
                @endif
                <h2 class="text-gray-600 text-lg font-bold mb-2">
                    <i class="mr-1 fa-solid fa-dollar-sign"></i>
                    {{
                        is_numeric(trim((string) ($workshop->price ?? ''))) && (float) trim((string) ($workshop->price ?? '')) > 0
                            ? number_format((float) trim((string) ($workshop->price ?? '')), 2, '.', '')
                            : 'Free'
                    }}
                </h2>
            </div>
        </div>
    </x-container>
</x-layout>
