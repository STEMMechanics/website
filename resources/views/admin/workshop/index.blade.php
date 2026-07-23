@php
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<x-layout>
    <x-mast title="Workshops" :tabs="$tabs" />

    <x-container>
        <div x-data="{ baseRoute: @js($monthMaterialsPdfRoute), open: false, showCancelled: false, openDialog() { this.open = true }, closeDialog() { this.open = false }, buildUrl(scope) { const url = new URL(this.baseRoute, window.location.origin); url.searchParams.set('materials_scope', scope); return url.toString(); }, launch(scope) { window.open(this.buildUrl(scope), '_blank', 'noopener'); this.closeDialog(); } }">
            <x-ui.toolbar break="lg">
                <x-slot:left>
                    <div class="flex justify-between w-full items-center gap-2">
                        <x-ui.button href="{{ route('admin.workshop.create') }}" class="w-full sm:w-auto">Create</x-ui.button>
                        <div class="flex items-center gap-2">
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
                                    aria-haspopup="dialog"
                                    x-on:click.prevent="openDialog()"
                            >
                                <i class="fa-solid fa-clipboard-list"></i>
                            </x-ui.button>
                        </div>
                    </div>
                </x-slot:left>
                <x-slot:right>
                    <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
                        @if($view === 'month')
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 lg:justify-start">
                                <x-ui.button href="{{ $previousMonthRoute }}" color="outline" class="px-4 py-2" title="Previous month" aria-label="Previous month">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </x-ui.button>
                                <div class="min-w-36 text-center text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $currentMonthLabel }}</div>
                                <x-ui.button href="{{ $nextMonthRoute }}" color="outline" class="px-4 py-2" title="Next month" aria-label="Next month">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </x-ui.button>
                            </div>
                            <x-ui.checkbox
                                name="show_cancelled"
                                value="1"
                                label="Show cancelled"
                                label-class="whitespace-nowrap"
                                :noWrapper="true"
                                :inline="true"
                                x-model="showCancelled"
                            />
                        @endif
                        <x-ui.search name="search" label="Search" />
                    </div>
                </x-slot:right>
            </x-ui.toolbar>

            <template x-teleport="body">
                <div
                    x-show="open"
                    x-cloak
                    class="fixed inset-0 z-[280] flex items-end justify-center bg-black/50 p-4 sm:items-center"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="materials-summary-dialog-title"
                    @click.self="closeDialog()"
                    @keydown.escape.window="if (open) { closeDialog() }"
                >
                    <div class="flex w-full max-w-xl flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div class="border-b border-gray-200 px-6 py-5">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <div class="text-xs font-semibold uppercase tracking-wide text-primary-color">Materials summary PDF</div>
                                    <h2 id="materials-summary-dialog-title" class="mt-1 text-xl font-bold text-gray-900">Choose workshop scope</h2>
                                    <p class="mt-2 text-sm leading-6 text-gray-600">
                                        Generate a PDF for every workshop in {{ $currentMonthLabel }} or only the workshops that are still upcoming.
                                    </p>
                                </div>
                                <button type="button" class="text-gray-500 transition hover:text-gray-900" @click="closeDialog()" aria-label="Close materials summary dialog">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </button>
                            </div>
                        </div>

                        <div class="grid gap-3 border-b border-gray-200 px-6 py-6 sm:grid-cols-2">
                            <button
                                type="button"
                                class="flex w-full flex-col items-start gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4 text-left transition hover:border-primary-color hover:bg-primary-color-light/10"
                                @click="launch('all')"
                            >
                                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                    <i class="fa-solid fa-calendar-days text-primary-color"></i>
                                    <span>All monthly workshops</span>
                                </div>
                                <div class="text-sm leading-5 text-gray-600">
                                    Includes every workshop in {{ $currentMonthLabel }}.
                                </div>
                            </button>

                            <button
                                type="button"
                                class="flex w-full flex-col items-start gap-2 rounded-xl border border-primary-color bg-primary-color-light/10 px-4 py-4 text-left transition hover:border-primary-color-dark hover:bg-primary-color-light/20"
                                @click="launch('upcoming')"
                            >
                                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                    <i class="fa-solid fa-arrow-up-right-dots text-primary-color"></i>
                                    <span>Upcoming workshops</span>
                                </div>
                                <div class="text-sm leading-5 text-gray-600">
                                    Starts from now through the rest of {{ $currentMonthLabel }}.
                                </div>
                            </button>
                        </div>

                        <div class="flex justify-end px-6 py-4">
                            <button type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" @click="closeDialog()">Cancel</button>
                        </div>
                    </div>
                </div>
            </template>

        @if($view === 'month')
            @php
                $calendarDays = collect($calendarWeeks)->flatten(1)->filter(fn (array $day): bool => (bool) ($day['in_month'] ?? false))->values();
                $hasSchoolHolidayDays = $calendarDays->contains(fn (array $day): bool => (bool) ($day['is_school_holiday'] ?? false));
                $adminCalendarStatus = function ($workshop): array {
                    $statusClass = (string) $workshop->status;
                    $statusTitle = $workshop->adminStatusLabel();
                    $statusShortTitle = match ($statusClass) {
                        'scheduled' => 'Soon',
                        'cancelled' => 'Canc.',
                        default => $statusTitle,
                    };

                    if ($statusClass === 'scheduled') {
                        $statusClass = 'soon';
                        $statusTitle = 'Opens Soon';
                    } elseif ($workshop->isPrivate() && $statusClass === 'open') {
                        $statusClass = 'private';
                        $statusTitle = 'Private';
                        $statusShortTitle = 'Priv.';
                    } elseif ((bool) ($workshop->is_hidden ?? false) && ! in_array($statusClass, ['cancelled', 'draft'], true)) {
                        $statusClass = 'hidden';
                        $statusTitle = 'Hidden';
                        $statusShortTitle = 'Hid.';
                    }

                    return [
                        'class' => $statusClass,
                        'title' => $statusTitle,
                        'short_title' => $statusShortTitle,
                    ];
                };
            @endphp

            @if($hasSchoolHolidayDays)
                <div class="mt-4 flex items-center gap-2 text-sm text-gray-600">
                    <span class="inline-block h-5 w-5 rounded border border-amber-400 bg-amber-50"></span>
                    <span class="italic text-xs font-semibold">{{ $schoolHolidayLabel ?? 'School holidays' }}</span>
                </div>
            @endif

            <div class="mt-6 space-y-4 lg:hidden">
                <div class="overflow-hidden border border-gray-200 bg-white">
                    <div class="divide-y divide-gray-200">
                        @foreach($calendarDays as $day)
                            @php
                                $mobileDayClasses = ['px-4 py-3'];
                                if ((bool) ($day['is_school_holiday'] ?? false)) {
                                    $mobileDayClasses[] = 'bg-amber-50';
                                } elseif ($day['is_today']) {
                                    $mobileDayClasses[] = 'bg-primary-color/5';
                                }
                            @endphp
                            <div class="{{ implode(' ', $mobileDayClasses) }}">
                                <div class="flex items-center justify-between gap-3">
                                    <div class="text-sm font-semibold {{ $day['is_today'] ? 'text-primary-color' : 'text-gray-900' }}">
                                        {{ \Illuminate\Support\Carbon::parse($day['date'])->format('D j M') }}
                                    </div>
                                </div>

                                <div class="mt-2 space-y-2">
                                    @forelse($day['workshops'] as $workshop)
                                        @php
                                            $status = $adminCalendarStatus($workshop);
                                        @endphp
                                        <a
                                            href="{{ route('admin.workshop.edit', $workshop) }}"
                                            @if((string) $workshop->status === 'cancelled') x-show="showCancelled" x-cloak @endif
                                            class="block rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs text-gray-700 hover:border-primary-color hover:bg-primary-color-light/10 hover:text-primary-color-dark"
                                        >
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="font-semibold text-gray-900">{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</div>
                                                    <div class="whitespace-normal wrap-break-word leading-snug">{{ $workshop->title }}</div>
                                                    <div class="mt-0.5 text-[11px] text-gray-500">{{ $workshop->getPublicLocationLabel() }}</div>
                                                </div>
                                                <div class="shrink-0 rounded-full border border-white/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white sm-banner-{{ strtolower($status['class']) }}" title="{{ $status['title'] }}">{{ $status['short_title'] }}</div>
                                            </div>
                                        </a>
                                    @empty
                                        <div class="text-sm text-gray-500">--</div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="mt-6 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white lg:block">
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
                                    @php
                                        $isSchoolHoliday = (bool) ($day['is_school_holiday'] ?? false);
                                        $dayCellClass = $day['in_month']
                                            ? ($isSchoolHoliday ? 'bg-amber-50' : ($day['is_today'] ? 'bg-primary-color' : 'bg-white'))
                                            : 'bg-gray-50 text-gray-400';
                                        $dayLabelClass = $day['is_today'] && ! $isSchoolHoliday
                                            ? 'text-white'
                                            : ($day['in_month'] ? ($day['is_today'] ? 'text-primary-color' : 'text-gray-900') : 'text-gray-400');
                                    @endphp
                                    <td class="align-top p-2 {{ $dayCellClass }}">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="inline-flex h-8 w-8 items-center justify-center text-sm font-semibold {{ $dayLabelClass }}">
                                                {{ $day['label'] }}
                                            </div>
                                        </div>
                                        <div class="mt-2 space-y-2">
                                            @forelse($day['workshops'] as $workshop)
                                                @php
                                                    $status = $adminCalendarStatus($workshop);
                                                @endphp
                                                <a
                                                    href="{{ route('admin.workshop.edit', $workshop) }}"
                                                    @if((string) $workshop->status === 'cancelled') x-show="showCancelled" x-cloak @endif
                                                    class="block rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-left text-xs text-gray-700 hover:border-primary-color hover:bg-primary-color-light/10 hover:text-primary-color-dark"
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="w-full">
                                                            <div class="flex justify-between items-center">
                                                                <div class="font-semibold text-gray-900">{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</div>
                                                                <div class="shrink-0 rounded-full border border-white/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white sm-banner-{{ strtolower($status['class']) }}" title="{{ $status['title'] }}">{{ $status['short_title'] }}</div>
                                                            </div>
                                                            <div class="whitespace-normal wrap-break-word leading-snug">{{ $workshop->title }}</div>
                                                            <div class="mt-0.5 text-[11px] text-gray-500">{{ $workshop->getPublicLocationLabel() }}</div>
                                                        </div>
                                                    </div>
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
                                        <div class="w-12 text-center hidden sm:inline-block">
                                            <img src="{{ $workshop->hero->thumbnail }}" class="max-h-12 max-w-12 -ml-2 -my-3 mr-3 inline rounded" alt="{{ $workshop->hero->title }}" />
                                        </div>
                                        <div>
                                            <div class="inline-flex max-w-full items-center gap-1.5 align-middle">
                                                <a href="{{ route('admin.workshop.edit', $workshop) }}" class="min-w-0 whitespace-normal text-gray-900 hover:text-primary-color">{{ $workshop->title }}</a>
                                                @if((bool) ($workshop->is_hidden ?? false))
                                                    <i class="fa-solid fa-eye-slash shrink-0 text-xs text-gray-400" title="Hidden workshop" aria-label="Hidden workshop"></i>
                                                @endif
                                            </div>
                                            <div class="lg:hidden text-xs text-gray-500">{{ $workshop->getLocationName() }} ({{ $workshop->adminStatusLabel() }})</div>
                                            <div class="md:hidden text-xs text-gray-500">{{ \Carbon\Carbon::parse($workshop->starts_at)->format('j/m/Y g:i a') }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell whitespace-nowrap text-center">{{ $workshop->adminStatusLabel() }}</td>
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
                                    <a href="{{ route('admin.workshop.photos', $workshop) }}" class="hover:text-primary-color" title="Photos"><i class="fa-solid fa-images"></i></a>
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
        </div>
    </x-container>
</x-layout>
