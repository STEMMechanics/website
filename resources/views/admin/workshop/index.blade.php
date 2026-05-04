@php
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<x-layout>
    <x-mast title="Workshops" :tabs="$tabs" />

    <x-container>
        <x-ui.toolbar>
            <x-slot:left class="flex-0">
                <x-ui.button href="{{ route('admin.workshop.create') }}">Create</x-ui.button>
            </x-slot:left>
            <x-slot:right>
                    <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
                        @if($view === 'month')
                        <div class="flex flex-wrap items-center gap-2">
                            <x-ui.button
                                href="{{ $monthCalendarPdfRoute }}"
                                target="_blank"
                                color="outline"
                                class="h-10 w-10 shrink-0 px-0"
                                title="Calendar PDF"
                                aria-label="Calendar PDF"
                            >
                                <i class="fa-regular fa-calendar"></i>
                            </x-ui.button>
                            <x-ui.button
                                href="{{ $monthPickListsPdfRoute }}"
                                target="_blank"
                                color="outline"
                                class="h-10 w-10 shrink-0 px-0"
                                title="Pick Lists PDF"
                                aria-label="Pick Lists PDF"
                            >
                                <i class="fa-regular fa-file-pdf"></i>
                            </x-ui.button>
                            <x-ui.button
                                href="{{ $monthMaterialsPdfRoute }}"
                                target="_blank"
                                color="outline"
                                class="h-10 w-10 shrink-0 px-0"
                                title="Materials Summary PDF"
                                aria-label="Materials Summary PDF"
                            >
                                <i class="fa-solid fa-clipboard-list"></i>
                            </x-ui.button>
                        </div>
                        <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 lg:justify-start">
                            <x-ui.button href="{{ $previousMonthRoute }}" color="outline" class="px-4 py-2" title="Previous month" aria-label="Previous month">
                                <i class="fa-solid fa-chevron-left"></i>
                            </x-ui.button>
                            <div class="min-w-36 text-center text-sm font-semibold text-gray-900">{{ $currentMonthLabel }}</div>
                            <x-ui.button href="{{ $nextMonthRoute }}" color="outline" class="px-4 py-2" title="Next month" aria-label="Next month">
                                <i class="fa-solid fa-chevron-right"></i>
                            </x-ui.button>
                        </div>
                    @endif
                    <x-ui.search name="search" label="Search" />
                </div>
            </x-slot:right>
        </x-ui.toolbar>

        @if($view === 'month')
            <div class="mt-6 overflow-x-auto rounded-xl border border-gray-200 bg-white">
                <table class="min-w-245 w-full table-fixed border-collapse">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                            @foreach($weekdays as $weekday)
                                <th class="px-3 py-2 text-center font-semibold">{{ $weekday }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 bg-gray-200">
                        @foreach($calendarWeeks as $week)
                            <tr>
                                @foreach($week as $day)
                                    <td class="align-top p-2 {{ $day['in_month'] ? ($day['is_today'] ? 'bg-primary-color' : 'bg-white') : 'bg-gray-50 text-gray-400' }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="inline-flex h-8 w-8 items-center justify-center text-sm font-semibold {{ $day['is_today'] ? 'text-white' : ($day['in_month'] ? 'text-gray-900' : 'text-gray-400') }}">
                                                {{ $day['label'] }}
                                            </div>
                                        </div>
                                        <div class="mt-2 space-y-2">
                                            @forelse($day['workshops'] as $workshop)
                                                <a href="{{ route('admin.workshop.edit', $workshop) }}" class="block rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-left text-xs text-gray-700 hover:border-primary-color hover:bg-primary-color-light/10 hover:text-primary-color-dark">
                                                    <div class="font-semibold text-gray-900">{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</div>
                                                    <div class="whitespace-normal wrap-break-word leading-snug">{{ $workshop->title }}</div>
                                                    <div class="mt-0.5 text-[11px] text-gray-500">{{ $workshop->getLocationName() }}</div>
                                                </a>
                                            @empty
                                                <div class="min-h-16"></div>
                                            @endforelse
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(! $hasMonthWorkshops)
                <p class="mt-4 text-sm text-gray-500">
                    {{ $search !== '' ? 'No workshops in this month match the current search.' : 'No workshops in this month.' }}
                </p>
            @endif
        @else
            @if($workshops->isEmpty())
                <x-none-found item="workshops" search="{{ $search }}" />
            @else
                <x-ui.table>
                    <x-slot:header>
                        <th>Title</th>
                        <th class="hidden lg:table-cell">Status</th>
                        <th class="hidden lg:table-cell">Location</th>
                        <th class="hidden md:table-cell">Starts</th>
                        <th>Action</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($workshops as $workshop)
                            <tr>
                                <td>
                                    <div class="flex items-center">
                                        <div class="w-12 text-center">
                                            <img src="{{ $workshop->hero->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 mr-3 inline rounded" alt="{{ $workshop->hero->title }}" />
                                        </div>
                                        <div>
                                            <a href="{{ route('admin.workshop.edit', $workshop) }}" class="whitespace-normal text-gray-900 hover:text-primary-color">{{ $workshop->title }}</a>
                                            <div class="lg:hidden text-xs text-gray-500">{{ $workshop->getLocationName() }} ({{ $workshop->publicStatusLabel() }})</div>
                                            <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('j/m/Y g:i a') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell whitespace-nowrap text-center">{{ $workshop->publicStatusLabel() }}</td>
                                <td class="hidden lg:table-cell">{{ $workshop->getLocationName() }}</td>
                                <td class="hidden md:table-cell">
                                    <span class="block xl:inline whitespace-no-wrap">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('M j Y') }}</span><span class="hidden xl:inline">, </span><span class="block xl:inline whitespace-no-wrap">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('g:i a') }}</span>
                                </td>
                                <td>
                                    <div class="flex justify-center gap-3">
                                    <a href="{{ route('admin.workshop.edit', $workshop) }}" class="hover:text-primary-color" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                    @if($workshop->registration === 'tickets')
                                        <a href="{{ route('admin.workshop.tickets', $workshop) }}" class="hover:text-primary-color" title="View tickets"><i class="fa-solid fa-ticket"></i></a>
                                    @endif
                                    @if($workshop->registration === 'interest' || (int) ($workshop->interests_count ?? 0) > 0)
                                        <a href="{{ route('admin.workshop.interests', $workshop) }}" class="inline-flex items-center gap-1 hover:text-primary-color" title="View interest registrations">
                                            <i class="fa-solid fa-thumbs-up"></i>
                                        </a>
                                    @endif
                                    <a href="{{ route('admin.workshop.attendance', $workshop) }}" class="hover:text-primary-color" title="Attendance"><i class="fa-solid fa-user-check"></i></a>
                                    <a href="{{ route('admin.workshop.pick-list', $workshop) }}" class="hover:text-primary-color" title="Pick List"><i class="fa-solid fa-list-check"></i></a>
                                    @if((string) $workshop->status !== 'draft')
                                        <a href="#" class="hover:text-primary-color" title="Copy public page link" x-data x-on:click.prevent="SM.copyToClipboard(@js(route('workshop.show', $workshop)))"><i class="fa-solid fa-link"></i></a>
                                    @endif
                                    <a href="{{ route('admin.workshop.duplicate', $workshop) }}" class="hover:text-primary-color" title="Duplicate"><i class="fa-regular fa-copy"></i></a>
                                    <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                    </div>
                                </td>
                            </tr>
                      @endforeach
                    </x-slot:body>
                </x-ui.table>

                {{ $workshops->appends(request()->query())->links() }}
            @endif
        @endif
    </x-container>
</x-layout>
