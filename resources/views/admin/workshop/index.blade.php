@php
    $weekdays = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
@endphp

<x-layout>
    <x-mast title="Workshops" :tabs="$tabs"><x-slot:actions><x-ui.button color="mast" href="{{ route('admin.workshop.create') }}" >Create</x-ui.button>
<x-ui.button color="mast"
                                    href="{{ route('admin.workshop-flyer.create') }}"
                                    class="w-8.5 shrink-0 px-0!"
                                    title="Promotional Flyer"
                                    aria-label="Promotional Flyer"
                            >
                                <span class="inline-flex h-6 items-center justify-center"><i class="fa-solid fa-print"></i></span>
                            </x-ui.button>
<x-ui.button color="mast"
                                    href="{{ $monthCalendarPdfRoute }}"
                                    target="_blank"
                                    class="w-8.5 shrink-0 px-0!"
                                    title="Calendar PDF"
                                    aria-label="Calendar PDF"
                            >
                                <span class="inline-flex h-6 items-center justify-center"><i class="fa-regular fa-calendar"></i></span>
                            </x-ui.button>
<x-ui.button color="mast"
                                    href="{{ $monthPickListsPdfRoute }}"
                                    target="_blank"
                                    class="w-8.5 shrink-0 px-0!"
                                    title="Pick Lists PDF"
                                    aria-label="Pick Lists PDF"
                            >
                                <span class="inline-flex h-6 items-center justify-center"><i class="fa-regular fa-file-pdf"></i></span>
                            </x-ui.button>
<x-ui.button color="mast"
                                    href="{{ $monthMaterialsPdfRoute }}"
                                    target="_blank"
                                    class="w-8.5 shrink-0 px-0!"
                                    title="Materials Summary PDF"
                                    aria-label="Materials Summary PDF"
                                    aria-haspopup="dialog"
                                    x-data x-on:click.prevent="$dispatch('open-workshop-materials')"
                            >
                                <span class="inline-flex h-6 items-center justify-center"><i class="fa-solid fa-clipboard-list"></i></span>
                            </x-ui.button></x-slot:actions></x-mast>

    <x-container class="py-5 sm:py-8">
        <x-finance.attention-notice kind="workshops" />
        <x-ui.dynamic-list name="admin-workshop-index" :show-presets="$view === 'list'">

        <div x-on:open-workshop-materials.window="openDialog()" x-data="{ baseRoute: @js($monthMaterialsPdfRoute), open: false, showCancelled: @js(request()->boolean('show_cancelled')), hoveredWorkshop: null, openDialog() { this.open = true }, closeDialog() { this.open = false }, buildUrl(scope) { const url = new URL(this.baseRoute, window.location.origin); url.searchParams.set('materials_scope', scope); return url.toString(); }, launch(scope) { window.open(this.buildUrl(scope), '_blank', 'noopener'); this.closeDialog(); } }">

                    <div class="flex w-full flex-col gap-3 lg:flex-row lg:items-center lg:justify-end">
                        @if($view === 'month')
                            <div class="flex items-center justify-between gap-2 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 lg:justify-start">
                                <x-ui.button data-dynamic-link href="{{ $previousMonthRoute }}" color="outline" class="px-4 py-2" title="Previous month" aria-label="Previous month">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </x-ui.button>
                                <div class="min-w-36 text-center text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $currentMonthLabel }}</div>
                                <x-ui.button data-dynamic-link href="{{ $nextMonthRoute }}" color="outline" class="px-4 py-2" title="Next month" aria-label="Next month">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </x-ui.button>
                            </div>

                        @endif

                    </div>

            @if($view === 'list')
                <x-ui.collection-controls class="my-5" />
            @endif

            <template x-teleport="body">
                <div
                    x-show="open"
                    x-cloak
                    class="fixed inset-0 z-280 flex items-end justify-center bg-black/50 p-4 sm:items-center"
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
                                <x-ui.button variant="plain" type="button" class="text-gray-500 transition hover:text-gray-900" x-on:click="closeDialog()" aria-label="Close materials summary dialog">
                                    <i class="fa-solid fa-xmark text-lg"></i>
                                </x-ui.button>
                            </div>
                        </div>

                        <div class="grid gap-3 border-b border-gray-200 px-6 py-6 sm:grid-cols-2">
                            <x-ui.button variant="plain"
                                type="button"
                                class="flex w-full flex-col items-start gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-4 text-left transition hover:border-primary-color hover:bg-primary-color-light/10"
                                x-on:click="launch('all')"
                            >
                                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                    <i class="fa-solid fa-calendar-days text-primary-color"></i>
                                    <span>All monthly workshops</span>
                                </div>
                                <div class="text-sm leading-5 text-gray-600">
                                    Includes every workshop in {{ $currentMonthLabel }}.
                                </div>
                            </x-ui.button>

                            <x-ui.button variant="plain"
                                type="button"
                                class="flex w-full flex-col items-start gap-2 rounded-xl border border-primary-color bg-primary-color-light/10 px-4 py-4 text-left transition hover:border-primary-color-dark hover:bg-primary-color-light/20"
                                x-on:click="launch('upcoming')"
                            >
                                <div class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                    <i class="fa-solid fa-arrow-up-right-dots text-primary-color"></i>
                                    <span>Upcoming workshops</span>
                                </div>
                                <div class="text-sm leading-5 text-gray-600">
                                    Starts from now through the rest of {{ $currentMonthLabel }}.
                                </div>
                            </x-ui.button>
                        </div>

                        <div class="flex justify-end px-6 py-4">
                            <x-ui.button variant="plain" type="button" class="rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 transition hover:bg-gray-50" x-on:click="closeDialog()">Cancel</x-ui.button>
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
                                                    <div class="font-semibold text-gray-900">{{ $workshop->starts_at?->toDateString() === $day['date'] ? $workshop->starts_at->format('g:i a') : 'Continues' }}</div>
                                                    <div class="whitespace-normal wrap-break-word leading-snug">{{ $workshop->title }}</div>
                                                    <div class="mt-0.5 text-[11px] text-gray-500">{{ $workshop->getPublicLocationLabel() }}</div>
                                                </div>
                                                <x-ui.workshop-status-badge :status="$status['class']" class="shrink-0" title="{{ $status['title'] }}">{{ $status['short_title'] }}</x-ui.workshop-status-badge>
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

            <div
                class="mt-6 hidden overflow-x-auto rounded-xl border border-gray-200 bg-white lg:block"
                x-on:mouseover="hoveredWorkshop = $event.target.closest('[data-workshop-key]')?.dataset.workshopKey ?? null"
                x-on:mouseleave="hoveredWorkshop = null"
            >
                <x-ui.table variant="plain" table-class="min-w-245 w-full table-fixed border-collapse">
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
                                            @forelse($day['workshop_lanes'] as $lane => $workshop)
                                                @if($workshop === null)
                                                    <div class="min-h-18" data-calendar-lane="{{ $lane }}" data-calendar-placeholder aria-hidden="true"></div>
                                                @else
                                                @php
                                                    $status = $adminCalendarStatus($workshop);
                                                    $continuation = $workshop->calendarContinuationForDate($day['date']);
                                                @endphp
                                                <a
                                                    href="{{ route('admin.workshop.edit', $workshop) }}"
                                                    data-calendar-lane="{{ $lane }}"
                                                    data-workshop-key="{{ $workshop->getKey() }}"
                                                    style="cursor: pointer;"
                                                    x-bind:class="{
                                                        'border-primary-color! bg-primary-color-light/10! text-primary-color-dark!': hoveredWorkshop === @js((string) $workshop->getKey()),
                                                        'invisible pointer-events-none': @js((string) $workshop->status === 'cancelled') && ! showCancelled
                                                    }"
                                                    @class([
                                                        'relative block min-h-18 cursor-pointer rounded-md border border-gray-200 bg-gray-50 px-2 py-1 text-left text-xs text-gray-700 hover:border-primary-color hover:bg-primary-color-light/10 hover:text-primary-color-dark',
                                                        'lg:relative lg:z-10 lg:ml-[-9px] lg:rounded-l-none lg:border-l-0 lg:pl-4' => $continuation['before'],
                                                        'lg:relative lg:z-10 lg:mr-[-9px] lg:rounded-r-none lg:border-r-0 lg:pr-4' => $continuation['after'],
                                                    ])
                                                >
                                                    <div class="flex items-start justify-between gap-2">
                                                        <div class="w-full">
                                                            <div class="flex justify-between items-center">
                                                                @if(! $continuation['before'])
                                                                    <div class="font-semibold text-gray-900"><x-ui.date-time>{{ $workshop->starts_at?->format('g:i a') ?? '-' }}</x-ui.date-time></div>
                                                                @elseif($continuation['ends'])
                                                                    <div class="absolute bottom-1 right-2 font-semibold text-gray-900">Ends <x-ui.date-time>{{ $workshop->ends_at?->format('g:i a') ?? '-' }}</x-ui.date-time></div>
                                                                @endif
                                                                @if(! $continuation['before'])
                                                                    <x-ui.workshop-status-badge :status="$status['class']" class="shrink-0" title="{{ $status['title'] }}">{{ $status['short_title'] }}</x-ui.workshop-status-badge>
                                                                @endif
                                                            </div>
                                                            @if($continuation['show_details'])
                                                                <div class="whitespace-normal wrap-break-word leading-snug">{{ $workshop->title }}</div>
                                                                <div class="mt-0.5 text-[11px] text-gray-500">{{ $workshop->getPublicLocationLabel() }}</div>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </a>
                                                @endif
                                            @empty
                                                <div class="min-h-16"></div>
                                            @endforelse
                                        </div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </x-ui.table>
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
                <x-ui.table variant="listing">
                    <x-slot:header>
                        <th class="w-10 text-center border-r-0!">
                            <x-ui.checkbox id="admin-workshop-select-page" aria-label="Select all workshops on this page" :noWrapper="true" inputClass="mx-auto" />
                        </th>
                        <x-ui.list-heading class="border-l-0! pl-1!" label="Title" />
                        <x-ui.list-heading class="hidden lg:table-cell text-center!" label="Status" />
                        <x-ui.list-heading class="hidden lg:table-cell" label="Location" />
                        <x-ui.list-heading field="starts_at" class="hidden md:table-cell text-center!" label="Starts" />
                        <x-ui.list-heading class="text-center!" label="Actions" />
                    </x-slot:header>
                    <x-slot:body>
                        @foreach ($workshops as $workshop)
                            <tr>
                                <td class="text-center border-r-0!">
                                    <x-ui.checkbox value="{{ $workshop->id }}" label="Select {{ $workshop->title }}" :labelHidden="true" :noWrapper="true" inputClass="admin-workshop-select-item" />
                                </td>
                                <td class="border-l-0! pl-1!">
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
                                            <div class="md:hidden text-xs text-gray-500"><x-ui.date-time>{{ \Carbon\Carbon::parse($workshop->starts_at)->format('j/m/Y g:i a') }}</x-ui.date-time></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell whitespace-nowrap text-center!">{{ $workshop->adminStatusLabel() }}</td>
                                <td class="hidden lg:table-cell">{{ $workshop->getLocationName() }}</td>
                                <td class="hidden md:table-cell text-center!">
                                    <span class="block xl:inline whitespace-nowrap"><x-ui.date-time>{{ \Carbon\Carbon::parse($workshop->starts_at)->format('M j Y') }}</x-ui.date-time></span><span class="hidden xl:inline">, </span><span class="block xl:inline whitespace-nowrap"><x-ui.date-time>{{ \Carbon\Carbon::parse($workshop->starts_at)->format('g:i a') }}</x-ui.date-time></span>
                                </td>
                                <td class="text-center!">
                                    <x-ui.row-actions>
                                    <x-ui.row-action label="Edit" icon="fa-solid fa-pen-to-square" tone="primary" href="{{ route('admin.workshop.edit', $workshop) }}" />
                                    @if($workshop->registration === 'tickets')
                                        <x-ui.row-action label="View tickets" icon="fa-solid fa-ticket" tone="neutral" href="{{ route('admin.workshop.tickets', $workshop) }}" />
                                    @endif
                                    @if($workshop->registration === 'interest' || (int) ($workshop->interests_count ?? 0) > 0)
                                        <x-ui.row-action label="View interest registrations" icon="fa-solid fa-thumbs-up" tone="neutral" href="{{ route('admin.workshop.interests', $workshop) }}" />
                                    @endif
                                    <x-ui.row-action label="Attendance" icon="fa-solid fa-user-check" tone="neutral" href="{{ route('admin.workshop.attendance', $workshop) }}" />
                                    <x-ui.row-action label="Run Sheet" icon="fa-solid fa-list-check" tone="neutral" href="{{ route('admin.workshop.run-sheet', $workshop) }}" />
                                    <x-ui.row-action label="Photos" icon="fa-solid fa-images" tone="neutral" href="{{ route('admin.workshop.photos', $workshop) }}" />
                                    @if((string) $workshop->status !== 'draft')
                                        <x-ui.row-action label="Copy public page link" icon="fa-solid fa-link" tone="neutral" x-data x-on:click.prevent="SM.copyToClipboard(@js(route('workshop.show', $workshop)))" />
                                    @endif
                                    <x-ui.row-action label="Duplicate" icon="fa-regular fa-copy" tone="neutral" href="{{ route('admin.workshop.duplicate', $workshop) }}" />
                                    <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete workshop?', 'Are you sure you want to delete this workshop? This action cannot be undone', '{{ route('admin.workshop.destroy', $workshop) }}')" />
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                      @endforeach
                    </x-slot:body>
                </x-ui.table>


            @endif
            <x-ui.list-pagination :paginator="$workshops">
                <x-slot:actions>
            <form id="admin-workshop-bulk-form" data-bulk-open="workshop-bulk-edit-dialog" method="POST" action="{{ route('admin.workshop.bulk.select') }}">
                @csrf
                <div id="admin-workshop-bulk-inputs"></div>
                <div id="admin-workshop-selection-toolbar" class="sm-list-footer-selection" data-selected="false">
                    <x-ui.bulk-edit-button type="submit" id="admin-workshop-bulk-edit" :count="0" class="px-3 sm:px-8" disabled />
                </div>
            </form>
                </x-slot:actions>
            </x-ui.list-pagination>

        @endif
        </div>

        </x-ui.dynamic-list>
        <x-ui.bulk-editor id="workshop-bulk-edit-dialog" title="Bulk edit workshops" loader-id="workshop-bulk-loader" list="admin-workshop-index" selection-key="admin-workshop-bulk-selection" selection-field="workshop_ids[]" />
    </x-container>
</x-layout>

<script>
    SM.onDynamicList('admin-workshop-index', () => {
        const storageKey = 'admin-workshop-bulk-selection';
        const form = document.getElementById('admin-workshop-bulk-form');
        const inputs = document.getElementById('admin-workshop-bulk-inputs');
        const bulkEdit = document.getElementById('admin-workshop-bulk-edit');
        const selectPage = document.getElementById('admin-workshop-select-page');
        const itemCheckboxes = Array.from(document.querySelectorAll('.admin-workshop-select-item'));
        let selected = [];

        try {
            selected = JSON.parse(sessionStorage.getItem(storageKey) || '[]').map(String);
        } catch (error) {
            selected = [];
        }
        selected = [...new Set(selected)];

        @if(session('admin_workshop_bulk_clear_selection'))
            if (!window.SM.workshopSelectionCleared) selected = [];
            window.SM.workshopSelectionCleared = true;
        @endif

        let renderHeader = () => {};
        const render = () => {
            sessionStorage.setItem(storageKey, JSON.stringify(selected));
            itemCheckboxes.forEach((checkbox) => checkbox.checked = selected.includes(checkbox.value));
            renderHeader();
            const toolbar = document.getElementById('admin-workshop-selection-toolbar');
            if (toolbar) toolbar.dataset.selected = String(selected.length > 0);
            if (bulkEdit) { bulkEdit.disabled = selected.length === 0; bulkEdit.textContent = `Edit ${selected.length} ${selected.length === 1 ? 'item' : 'items'}`; }
            if (inputs) {
                inputs.replaceChildren(...selected.map((id) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'workshop_ids[]';
                    input.value = id;
                    return input;
                }));
            }
        };

        itemCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', () => {
            selected = checkbox.checked
                ? [...new Set([...selected, checkbox.value])]
                : selected.filter((id) => id !== checkbox.value);
            render();
        }));
        const selectionUrl = new URL(window.location.href);
        selectionUrl.searchParams.set('select_listing', '1');
        renderHeader = window.SMSelection.bindSelectionCycle({
            header: selectPage, pageIds: itemCheckboxes.map(input => input.value), getSelected: () => selected,
            setSelected(ids) {
                if (ids.length > 5000) { SM.banner('Selection limit', 'Select up to 5000 workshops at a time.', 'warning'); return false; }
                selected = ids; render();
            },
            key: window.SMSelection.selectionKey(selectionUrl),
            async loadMatching() {
                const response = await fetch(selectionUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
                const data = await response.json();
                if (!response.ok) throw new Error(data.message || 'Could not select workshops.');
                return data.names.map(String);
            },
            onError: error => SM.banner('Could not select workshops', error.message, 'danger'),
        });
        render();
    });
</script>
