<x-layout>
    <x-container>
        <x-ui.image-hero :image="$event->hero?->url" class="my-8" />
        <div class="flex sm:gap-16 gap-4 flex-col sm:flex-row">
            <div class="flex flex-col flex-1">
                <h1 class="text-3xl font-bold mb-6">{!! $event->title !!}</h1>
                <article class="content mb-4">{!! $event->content !!}</article>
                <x-ui.filelist class="mt-16" value="{!! $event->files()->orderBy('name')->get() !!}" />
            </div>
            <div class="flex flex-col sm:pt-8 basis-64 flex-grow-0 flex-shrink-0">
                @if($event->status === 'closed')
                    <div class="sm-registration-closed">Registration for this event has closed.</div>
                @elseif($event->status === 'full')
                    <div class="sm-registration-full">This workshop is currently full.</div>
                @elseif($event->status === 'private')
                    <div class="sm-registration-private">This is a private event. Please contact the organiser for details.</div>
                @elseif($event->status === 'scheduled')
                    <div class="sm-registration-scheduled">Registration for this workshop will open soon.</div>
                @elseif($event->status === 'cancelled')
                    <div class="sm-registration-cancelled">This workshop has been cancelled.</div>
                @elseif($event->registration === 'none')
                    <div class="sm-registration-none">Registration not required for this event. Arrive early to avoid disappointment as seating maybe limited.</div>
                @elseif($event->registration === 'link')
                    <x-ui.button href="{{ $event->registration_data }}" class="my-4">Register for Event</x-ui.button>
                @elseif($event->registration === 'email')
                    <div class="sm-registration-email">Registration for this event by emailing <a href="mailto:{{ $event->registration_data }}" class="link">{{ $event->registration_data }}</a>.</div>
                @elseif($event->registration === 'message')
                    <div class="sm-registration-message">{{ $event->registration_data }}</div>
                @endif
                @if(auth()->user()?->admin)
                    <x-ui.button class="mb-4" color="primary-outline" href="{{ route('admin.event.edit', $event) }}">Edit Workshop</x-ui.button>
                @endif
                <h2 class="text-gray-600 text-lg font-bold mt-4 mb-2"><i class="mr-1 fa-regular fa-calendar"></i> Date/Time</h2>
                <p class="text-gray-600 text-sm pl-6 mb-6">{!! implode('<br />', \App\Helpers::createTimeDurationStr($event->starts_at, $event->ends_at)) !!}</p>
                <h2 class="text-gray-600 text-lg font-bold mb-2"><i class="mr-1 fa-solid fa-location-dot"></i> Location</h2>
                    <div class="text-gray-600 text-sm pl-6 mb-6">
                        @if($event->location->url)
                            <a href="{{ $event->location->url }}" class="link">
                        @endif
                        <p>{{ $event->location->name }}</p>
                        @if($event->location->url)
                            </a>
                        @endif

                        @if($event->location->address_url)
                            <a href="{{ $event->location->address_url }}" class="link" target="_blank">
                        @endif
                        <p class="text-xs">{{ $event->location->address }}</p>
                        @if($event->location->address_url)
                            </a>
                        @endif
                    </div>
                <h2 class="text-gray-600 text-lg font-bold mb-2"><i class="mr-1 fa-regular fa-face-smile"></i> {{ isset($event->ages) && $event->ages !== '' ? 'Ages ' . $event->ages : 'All ages' }}</h2>
                @if(\App\Helpers::isUnderAge($event->ages))
                    <p class="text-gray-600 text-xs pl-3 ml-2 mb-6 border-l-4 border-l-yellow-400">Parental supervision may be required for children 8 years of age and under.</p>
                @endif
                <h2 class="text-gray-600 text-lg font-bold mb-2"><i class="mr-1 fa-solid fa-dollar-sign"></i> {{ isset($event->price) && $event->price !== '' && $event->price !== '0' ? $event->price : 'Free' }}</h2>
{{--                @if(isset($event->price) && $event->price !== '' && $event->price !== '0' && strtolower($event->price) !== 'free')--}}
{{--                    <p class="text-gray-600 text-xs pl-3 ml-2 mb-6 border-l-4 border-l-green-500">Payment by cash or EFTPOS accepted. Please ensure correct change.</p>--}}
{{--                @endif--}}
            </div>
        </div>
    </x-container>
</x-layout>
