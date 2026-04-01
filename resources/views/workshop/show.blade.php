@php
    $seoDescription = \Illuminate\Support\Str::limit(trim(strip_tags((string) ($workshop->content ?? ''))), 160, '...');
    $heroImageUrl = $workshop->hero?->url ? url((string) $workshop->hero->url) : null;
    $interestPrefillName = old('interest_name', trim((string) (auth()->user()?->getName() ?? '')));
    $interestPrefillEmail = old('interest_email', trim((string) (auth()->user()?->email ?? '')));
    $interestPrefillPhone = old('interest_phone', trim((string) (auth()->user()?->phone ?? '')));
    $interestModalOpen = $errors->has('interest_name') || $errors->has('interest_email') || $errors->has('interest_phone');
    $userHasInterest = $currentUserInterest !== null;

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
        'startDate' => $workshop->usesClassroomRegistration() && $workshop->courseScheduleDisplayLines() === ['Anytime']
            ? null
            : ($workshop->effectiveStartsAt()?->toIso8601String() ?? $workshop->starts_at?->toIso8601String()),
        'endDate' => $workshop->usesClassroomRegistration() && $workshop->courseScheduleDisplayLines() === ['Anytime']
            ? null
            : ($workshop->effectiveEndsAt()?->toIso8601String() ?? $workshop->ends_at?->toIso8601String()),
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
        'performer' => [
            '@type' => 'Organization',
            'name' => 'STEMMechanics',
            'url' => url('/'),
        ],
        'url' => route('workshop.show', $workshop),
    ];

    if ($heroImageUrl) {
        $eventJsonLd['image'] = [$heroImageUrl];
    }

    $rawPrice = trim((string) ($workshop->price ?? ''));
    $hasBookableOffer = ! $workshop->isPrivate()
        && in_array((string) ($workshop->registration ?? ''), ['tickets', 'classroom', 'link'], true);
    if ($hasBookableOffer && ($rawPrice === '' || is_numeric($rawPrice))) {
        $offerUrl = $workshop->registration === 'link' && trim((string) ($workshop->registration_data ?? '')) !== ''
            ? trim((string) $workshop->registration_data)
            : route('workshop.show', $workshop);
        $eventJsonLd['offers'] = [
            '@type' => 'Offer',
            'priceCurrency' => 'AUD',
            'price' => number_format((float) ($rawPrice === '' ? 0 : $rawPrice), 2, '.', ''),
            'availability' => $workshop->status === 'full'
                ? 'https://schema.org/SoldOut'
                : 'https://schema.org/InStock',
            'url' => $offerUrl,
        ];
    }
@endphp
@php
    $workshopContent = \App\Support\HtmlContentTransformer::collapseSectionsForDisplay((string) ($workshop->content ?? ''));
    $courseScheduleLines = $workshop->courseScheduleDisplayLines();
    $courseScheduleCadence = $workshop->courseScheduleCadenceLabel();
@endphp

    <x-layout
    :title="$workshop->title"
    :description="$seoDescription"
    :canonical="route('workshop.show', $workshop)"
    :noindex="(bool) $workshop->is_hidden"
    :jsonLd="$eventJsonLd"
>
    <x-mast>{{ $workshop->title }}</x-mast>
    <x-container>
        <x-ui.image-hero :image="$workshop->hero?->url" class="my-8" />
        <div class="flex sm:gap-16 gap-4 flex-col sm:flex-row">
            <div class="flex flex-col flex-1">
                <h1 class="text-3xl font-bold mb-6">{!! $workshop->title !!}</h1>
                <article class="content mb-4">{!! $workshopContent !!}</article>
                <x-ui.filelist class="mt-16" value="{!! $workshop->files()->orderBy('name')->get() !!}" />
            </div>
            <div class="flex flex-col sm:pt-8 basis-64 grow-0 shrink-0">
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
                @if($workshop->status === 'open')
                    @if($workshop->registration === 'tickets' && $availableTickets !== null)
                        @if((int) $availableTickets > 0)
                            <x-ui.button href="{{ route('workshop.ticket.flow.start', $workshop) }}" class="mb-2">Get Tickets</x-ui.button>
                            @if($workshop->requiresPrivateTicketCode())
                                <p class="text-xs text-gray-600 text-center mb-1 font-semibold">Access code required</p>
                            @endif
                            <p class="text-xs text-gray-600 text-center mb-2">
                                @if($availableTickets === null)
                                    Tickets available now.
                                @else
                                    {{ $availableTickets }} ticket{{ (int) $availableTickets === 1 ? '' : 's' }} remaining
                                @endif
                            </p>
                        @else
                            <div class="sm-registration-full">This workshop is currently full.</div>
                        @endif
                    @elseif($workshop->registration === 'classroom')
                        @if($availableTickets === null || (int) $availableTickets > 0)
                            <x-ui.button href="{{ route('workshop.ticket.flow.start', $workshop) }}" class="mb-2">Enrol Now</x-ui.button>
                            <p class="text-xs text-gray-600 text-center mb-2">
                                @if($availableTickets === null)
                                    Course access available now.
                                @else
                                    {{ $availableTickets }} space{{ (int) $availableTickets === 1 ? '' : 's' }} remaining
                                @endif
                            </p>
                        @else
                            <div class="sm-registration-full">This course is currently full.</div>
                        @endif
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
                    @elseif($workshop->registration === 'interest' && ($hasPrivateAccess ?? false))
                        <div class="flex flex-col mb-4">
                            @auth
                                <form method="POST" action="{{ route('workshop.interest', $workshop) }}">
                                    @csrf
                                    <input type="hidden" name="action" value="{{ $userHasInterest ? 'remove' : 'add' }}">
                                    <x-ui.button type="submit" class="w-full" color="{{ $userHasInterest ? 'primary-outline' : 'primary' }}">
                                        {{ $userHasInterest ? 'Cancel Interest' : "I'm Interested" }}
                                    </x-ui.button>
                                </form>
                            @else
                                <div x-data="{ interestModalOpen: {{ $interestModalOpen ? 'true' : 'false' }} }">
                                    <x-ui.button type="button" class="w-full" x-on:click="interestModalOpen = true">I'm Interested</x-ui.button>

                                    <div
                                        x-show="interestModalOpen"
                                        x-cloak
                                        class="fixed inset-0 z-50 flex items-center justify-center p-4"
                                        x-on:keydown.escape.window="interestModalOpen = false"
                                    >
                                        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" x-on:click="interestModalOpen = false"></div>
                                        <div class="relative z-10 w-full max-w-lg rounded-xl bg-white shadow-xl border border-gray-200 p-6">
                                            <div class="flex items-start justify-between gap-4">
                                                <div>
                                                    <h3 class="text-lg font-bold text-gray-900">I'm Interested</h3>
                                                    <p class="mt-2 text-sm text-gray-700">Leave your details and we’ll record your interest for this workshop.</p>
                                                </div>
                                                <button type="button" class="text-gray-500 hover:text-gray-700" x-on:click="interestModalOpen = false" aria-label="Close">
                                                    <i class="fa-solid fa-xmark"></i>
                                                </button>
                                            </div>

                                            <form method="POST" action="{{ route('workshop.interest', $workshop) }}" class="mt-6">
                                                @csrf
                                                <x-ui.input label="Name" name="interest_name" value="{{ $interestPrefillName }}" error="{{ $errors->first('interest_name') }}" required autofocus />
                                                <x-ui.input label="Email" name="interest_email" value="{{ $interestPrefillEmail }}" error="{{ $errors->first('interest_email') }}" required />
                                                <x-ui.input label="Phone" name="interest_phone" value="{{ $interestPrefillPhone }}" error="{{ $errors->first('interest_phone') }}" />
                                                <div class="pt-2 flex justify-end gap-3">
                                                    <x-ui.button type="button" color="primary-outline" x-on:click="interestModalOpen = false">Cancel</x-ui.button>
                                                    <x-ui.button type="submit">Submit Interest</x-ui.button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            @endauth
                            <p class="text-xs text-gray-600 text-center mt-2">{{ (int)( ($interestCount ?? 0) + 1) }} interested so far</p>
                            @auth
                                @if($userHasInterest)
{{--                                    <p class="text-xs text-gray-500 text-center mt-1">You’re currently marked as interested.</p>--}}
                                @endif
                            @endauth
                        </div>
                    @elseif($workshop->registration === 'message')
                        <div class="sm-registration-message">{{ $workshop->registration_data }}</div>
                    @elseif($workshop->registration === 'classroom')
                        <div class="rounded-2xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-950">
                            <div class="font-semibold">Course registration</div>
                            <p class="mt-2 leading-6">
                                This workshop includes course registration. Sign in with the account linked to your registration group to open the course and join the live sessions.
                            </p>
                            @if($workshop->classSession)
                                <x-ui.button class="mt-4" href="{{ route('class.show', $workshop->classSession) }}">Open Course</x-ui.button>
                            @endif
                        </div>
                    @endif
                @endif
                @if(auth()->user()?->isAdmin())
                    <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                    @if($workshop->registration === 'interest' || (int) ($interestCount ?? 0) > 0)
                        <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.interests', $workshop) }}">View Interests</x-ui.button>
                    @endif
                    @if($workshop->pick_list_template_id)
                        <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.pick-list', $workshop) }}">Pick List</x-ui.button>
                    @endif
                    @if($adminCanViewTickets ?? false)
                        <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.tickets', $workshop) }}">View Tickets</x-ui.button>
                    @endif
                    <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.workshop.attendance', $workshop) }}">Attendance</x-ui.button>
                @endif
                <h2 class="text-gray-600 text-lg font-bold mt-4 mb-2"><i class="mr-1 fa-regular fa-calendar"></i> Date/Time</h2>
                @if($workshop->usesClassroomRegistration())
                    @if($courseScheduleCadence)
                        <p class="text-gray-600 text-sm pl-6 mb-2">This course streams {{ $courseScheduleCadence }}.</p>
                    @endif
                    <div class="text-gray-600 text-xs pl-6 mb-6">
                        @if($courseScheduleLines === ['Anytime'])
                            <p>Anytime</p>
                        @else
                            <ul class="list-disc list-outside pl-5 space-y-1 marker:text-gray-900">
                                @foreach($courseScheduleLines as $line)
                                    <li>{{ $line }}</li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @else
                    <p class="text-gray-600 text-sm pl-6 mb-6">{!! implode('<br />', \App\Helpers::createTimeDurationStr($workshop->starts_at, $workshop->ends_at)) !!}</p>
                @endif
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
