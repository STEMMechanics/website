@php
    $isPast = (bool) ($isPast ?? request()->routeIs('workshop.past.index'));
    $view = (string) ($selectedView ?? 'cards');
    $isCalendarView = $view === 'calendar';
    $monthLabel = (string) ($currentMonthLabel ?? now()->format('F Y'));
    $selectedCategorySlug = (string) ($selectedCategorySlug ?? '');

    $calendarDays = collect($calendarWeeks ?? [])->flatten(1)->filter(fn (array $day): bool => (bool) ($day['in_month'] ?? false))->values();
    $hasSchoolHolidayDays = $calendarDays->contains(fn (array $day): bool => (bool) ($day['is_school_holiday'] ?? false));
@endphp

@push('head')
    <link rel="alternate" type="application/rss+xml" title="STEMMechanics Workshops RSS feed" href="{{ route('workshop.feed') }}">
@endpush

<x-layout
    :title="$isPast ? 'Past Workshops' : 'Workshops'"
    :description="$isPast
        ? 'Explore past STEMMechanics workshops and previous program sessions.'
        : 'Browse upcoming STEMMechanics workshops, event details, and registration options.'"
    :canonical="$isPast ? route('workshop.past.index') : route('workshop.index')"
>
    <x-mast title="Workshops" :tabs="$tabs" />
    <section class="bg-gray-100">
        <x-container class="pt-4">
            <div class="flex gap-3 justify-between">
                @if(! $isCalendarView && ($workshopCategories ?? collect())->isNotEmpty())
                    <form method="GET" action="{{ $isPast ? route('workshop.past.index') : route('workshop.index') }}" class="flex items-center gap-2">
                        <input type="hidden" name="view" value="cards">
                        <x-ui.select
                            id="workshop-category-filter"
                            label="Category"
                            name="category"
                            class="mb-0 w-64"
                            label-class="hidden sm:inline-block"
                            inline-label
                            onchange="this.form.submit()"
                        >
                            <option value="">All workshops</option>
                            @foreach($workshopCategories as $category)
                                <option value="{{ $category->slug }}" @selected($selectedCategorySlug === (string) $category->slug)>{{ $category->name }}</option>
                            @endforeach
                        </x-ui.select>
                    </form>
                @else
                    @if($hasSchoolHolidayDays)
                        <div class="mx-auto flex w-full items-center gap-2 text-sm text-gray-600">
                            <span class="inline-block h-6 w-6 rounded border border-amber-400 bg-amber-50"></span>
                            <span class="italic text-xs font-semibold">{{ $schoolHolidayLabel ?? 'School holidays' }}</span>
                        </div>
                    @endif
                @endif

                <div class="flex items-center justify-end gap-2">
                    <x-ui.button
                        href="{{ $toggleViewRoute }}"
                        color="outline"
                        class="px-3"
                        title="{{ $toggleViewTitle }}"
                        aria-label="{{ $toggleViewTitle }}"
                    >
                        <i class="{{ $toggleViewIcon }}"></i>
                        <span class="sr-only">{{ $toggleViewTitle }}</span>
                    </x-ui.button>
                    <x-ui.button
                        href="{{ route('workshop.feed') }}"
                        color="outline"
                        class="px-3"
                        title="RSS feed"
                        aria-label="RSS feed"
                    >
                        <i class="fa-solid fa-rss"></i>
                        <span class="sr-only">RSS feed</span>
                    </x-ui.button>
                </div>
            </div>
        </x-container>
        @if($isCalendarView)
            <x-container class="mt-6">
                <div class="mx-auto flex w-full items-center justify-between gap-3 rounded-lg border border-gray-200 bg-white px-3 py-2">
                    <x-ui.button href="{{ $previousMonthRoute }}" color="outline" class="px-4 py-2" title="Previous month" aria-label="Previous month">
                        <i class="fa-solid fa-chevron-left"></i>
                    </x-ui.button>
                    <div class="min-w-36 text-center text-sm font-semibold text-gray-900">{{ $monthLabel }}</div>
                    <x-ui.button href="{{ $nextMonthRoute }}" color="outline" class="px-4 py-2" title="Next month" aria-label="Next month">
                        <i class="fa-solid fa-chevron-right"></i>
                    </x-ui.button>
                </div>
            </x-container>

            @if(($calendarWeeks ?? collect())->isEmpty())
                <x-container class="mt-8">
                    <x-none-found item="workshops" />
                </x-container>
            @else
                <x-container class="mt-6 sm-view-calendar">
                    <div class="mx-auto w-full space-y-4 lg:hidden">
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
                                                    $statusClass = $workshop->publicStatus();
                                                    $statusTitle = $workshop->publicStatusLabel();
                                                    $statusShortTitle = $workshop->calendarStatusLabel();

                                                    if ($workshop->status === 'scheduled') {
                                                        $statusClass = 'soon';
                                                        $statusTitle = 'Opens Soon';
                                                        $statusShortTitle = 'Soon';
                                                    }
                                                @endphp
                                                <a href="{{ route('workshop.show', $workshop) }}" class="block rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-left text-xs text-gray-700 hover:border-primary-color hover:bg-primary-color-light/10 hover:text-primary-color-dark">
                                                    <div class="flex items-start justify-between gap-3">
                                                        <div class="min-w-0">
                                                            <div class="font-semibold text-gray-900">{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</div>
                                                            <div class="whitespace-normal wrap-break-word leading-snug">{{ $workshop->title }}</div>
                                                            <div class="mt-0.5 text-[11px] text-gray-500">{{ $workshop->getPublicLocationLabel() }}</div>
                                                        </div>
                                                        <div class="shrink-0 rounded-full border border-white/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white sm-banner-{{ strtolower($statusClass) }}" title="{{ $statusTitle }}">{{ $statusShortTitle }}</div>
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
                </x-container>

                <x-container class="mt-6">
                    <div class="mx-auto hidden w-full overflow-x-auto rounded-xl border border-gray-200 bg-white lg:block">
                        <table class="min-w-245 w-full table-fixed border-collapse">
                        <thead>
                            <tr class="border-b border-gray-200 bg-gray-50 text-[11px] font-semibold uppercase tracking-wide text-gray-500">
                                <th class="px-3 py-2 text-center font-semibold">Sun</th>
                                <th class="px-3 py-2 text-center font-semibold">Mon</th>
                                <th class="px-3 py-2 text-center font-semibold">Tue</th>
                                <th class="px-3 py-2 text-center font-semibold">Wed</th>
                                <th class="px-3 py-2 text-center font-semibold">Thu</th>
                                <th class="px-3 py-2 text-center font-semibold">Fri</th>
                                <th class="px-3 py-2 text-center font-semibold">Sat</th>
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
                                            <a href="{{ route('workshop.show', $workshop) }}" class="block rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-left text-xs text-gray-700 hover:border-primary-color hover:bg-primary-color-light/10 hover:text-primary-color-dark">
                                                        @php
                                                            $statusClass = $workshop->publicStatus();
                                                            $statusTitle = $workshop->publicStatusLabel();
                                                            $statusShortTitle = $workshop->calendarStatusLabel();

                                                            if ($workshop->status === 'scheduled') {
                                                                $statusClass = 'soon';
                                                                $statusTitle = 'Opens Soon';
                                                                $statusShortTitle = 'Soon';
                                                            }
                                                        @endphp
                                                        <div class="flex items-start justify-between gap-2">
                                                            <div class="w-full">
                                                                <div class="flex justify-between items-center">
                                                                    <div class="font-semibold text-gray-900">{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</div>
                                                                    <div class="shrink-0 rounded-full border border-white/50 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white sm-banner-{{ strtolower($statusClass) }}" title="{{ $statusTitle }}">{{ $statusShortTitle }}</div>
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
                </x-container>

                @if(! $hasMonthWorkshops)
                    <x-container class="mt-4">
                        <p class="text-sm text-gray-500">No workshops in this month.</p>
                    </x-container>
                @endif
            @endif
        @else
            @if($workshops->isEmpty())
                <x-container class="mt-8">
                    @if($selectedCategory ?? null)
                        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center shadow-sm">
                            <p class="text-lg font-semibold text-gray-900">No {{ $selectedCategory->name }} workshops found.</p>
                            <p class="mt-1 text-sm text-gray-500">Try another category or view all workshops.</p>
                            <x-ui.button href="{{ $isPast ? route('workshop.past.index') : route('workshop.index') }}" class="mt-4">View all workshops</x-ui.button>
                        </div>
                    @else
                        <x-none-found item="workshops" />
                    @endif
                </x-container>
            @else
                <x-container class="mt-4" inner-class="grid md:grid-cols-2 lg:grid-cols-3 gap-8 w-full sm-view-panels">
                    @foreach ($workshops as $workshop)
                        <x-panel-workshop :workshop="$workshop" data-early-bird-display="badge" />
                    @endforeach
                </x-container>
                <x-container>
                    {{ $workshops->appends(request()->query())->links() }}
                </x-container>
            @endif
        @endif
    </section>
</x-layout>
