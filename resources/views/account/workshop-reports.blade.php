<x-layout title="Workshop Reports">
    <x-mast description="Review workshop dates, locations, attendance numbers, and the photos available for your reporting.">
        Workshop Reports
    </x-mast>

    <x-container>
        <div class="my-8">
            @php
                $activeFilterCount = collect([
                    $filters['search'],
                    $filters['location'],
                    $filters['photos'] !== 'all' ? $filters['photos'] : '',
                    $filters['date_from'] !== '' || $filters['date_to'] !== '' ? 'date_range' : '',
                ])->filter(fn ($value) => $value !== '')->count();
            @endphp
            <div class="mb-5 flex justify-end">
                <x-ui.button color="outline" data-open-dialog="workshop-report-filters" aria-haspopup="dialog" aria-controls="workshop-report-filters" class="h-11 rounded-lg border-gray-300! shadow-none">
                    <i class="fa-solid fa-filter mr-2" aria-hidden="true"></i>Filter workshops
                    @if($activeFilterCount > 0)
                        <x-ui.badge color="sky" class="ml-2">{{ $activeFilterCount }}</x-ui.badge>
                    @endif
                </x-ui.button>
            </div>

            <x-ui.list-dialog id="workshop-report-filters" title="Filter workshops" kind="filters">
                <form method="GET" action="{{ route('account.workshop-report.index') }}" x-data="{}">
                    <div class="grid gap-4 p-5 sm:grid-cols-2">
                        <x-ui.input
                            type="search"
                            name="search"
                            label="Search"
                            placeholder="Workshop, location or organisation"
                            value="{{ $filters['search'] }}"
                            class="mb-0 sm:col-span-2"
                        />
                        <x-ui.select name="location" label="Location" class="mb-0">
                            <option value="">All locations</option>
                            @foreach($locationOptions as $location)
                                <option value="{{ $location->id }}" @selected($filters['location'] === (string) $location->id)>{{ $location->name }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-ui.select name="photos" label="Photos" class="mb-0" data-clear-value="all">
                            <option value="all" @selected($filters['photos'] === 'all')>With or without photos</option>
                            <option value="with" @selected($filters['photos'] === 'with')>With photos</option>
                            <option value="without" @selected($filters['photos'] === 'without')>Without photos</option>
                        </x-ui.select>
                        <x-ui.input type="date" name="date_from" label="From date" value="{{ $filters['date_from'] }}" class="mb-0" x-ref="reportDateFrom" />
                        <x-ui.input type="date" name="date_to" label="To date" value="{{ $filters['date_to'] }}" class="mb-0" x-ref="reportDateTo" />
                        <div class="sm:col-span-2">
                            <p class="mb-2 text-xs font-medium text-slate-500">Quick date range</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach($dateShortcuts as $dateShortcut)
                                    <x-ui.button
                                        variant="soft"
                                        color="secondary"
                                        size="small"
                                        type="button"
                                        data-range-from="{{ $dateShortcut['from'] }}"
                                        data-range-to="{{ $dateShortcut['to'] }}"
                                        x-on:click="$SM.updateDateRange($refs.reportDateFrom, $refs.reportDateTo, $el.dataset.rangeFrom, $el.dataset.rangeTo)"
                                    >
                                        {{ $dateShortcut['label'] }}
                                    </x-ui.button>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    <div class="sm-dialog-footer flex-col items-stretch sm:flex-row sm:items-center">
                        <x-ui.button color="outline" type="button" data-clear-filter-fields class="w-full sm:w-auto">Clear all</x-ui.button>
                        <x-ui.button color="outline" type="button" data-close-dialog class="w-full sm:w-auto">Cancel</x-ui.button>
                        <x-ui.button type="submit" icon="fa-solid fa-filter" label="Apply filters" class="w-full sm:w-auto" />
                    </div>
                </form>
            </x-ui.list-dialog>

            @if($workshops->isEmpty())
                <div class="rounded-2xl border border-dashed border-gray-300 bg-gray-50 px-5 py-10 text-center">
                    <i class="fa-solid fa-chart-column text-3xl text-gray-400" aria-hidden="true"></i>
                    <h2 class="mt-4 text-lg font-semibold text-gray-900">No workshops match these filters</h2>
                    <p class="mx-auto mt-2 max-w-xl text-sm text-gray-600">Try clearing one or more filters, or wait until STEMMechanics links another workshop to your account.</p>
                </div>
            @else
                <x-ui.table variant="listing" caption="Workshop reports" :mobile-cards="true">
                    <x-slot:header>
                        <th>Workshop</th>
                        <th>Location</th>
                        <th data-column-align="center">Attendance</th>
                        <th data-column-align="center">Images</th>
                        <th class="text-right!">Actions</th>
                    </x-slot:header>
                    <x-slot:body>
                        @foreach($workshops as $workshop)
                            @php($photos = $workshop->report_photos ?? [])
                            @php($galleryId = 'workshop-gallery-'.$workshop->getKey())
                            <tr>
                                <td data-label="Workshop">
                                    <div class="font-semibold text-gray-900">{{ $workshop->title }}</div>
                                    <div class="mt-1 space-y-1 text-xs text-gray-500">
                                        @foreach($workshop->report_schedule as $scheduleLine)
                                            <div>{{ $scheduleLine }}</div>
                                        @endforeach
                                    </div>
                                </td>
                                <td data-label="Location">
                                    <div class="font-medium text-gray-900">{{ $workshop->report_location_name }}</div>
                                    @if($workshop->report_location_address)
                                        <div class="mt-1 text-xs text-gray-500">{{ $workshop->report_location_address }}</div>
                                    @endif
                                </td>
                                <td data-label="Attendance" data-mobile-align="center" class="text-center! font-normal">
                                    {{ $workshop->report_attendance }}
                                </td>
                                <td data-label="Images" data-mobile-align="center" class="text-center!">
                                    @if($photos !== [])
                                        <x-ui.gallery :items="$photos" :downloads="true" :viewer-id="$galleryId" :visible-limit="3" />
                                    @else
                                        <span class="text-gray-500">—</span>
                                    @endif
                                </td>
                                <td data-label="Actions" data-mobile-actions class="text-right!">
                                    @if($photos !== [] || $workshop->isPubliclyVisible() || $workshop->hero || filled($workshop->report_promotion_copy))
                                        <x-ui.row-actions class="justify-end!">
                                            @if($photos !== [])
                                                <x-ui.row-action label="View photos" icon="fa-solid fa-images" tone="neutral" type="button" x-on:click="$dispatch('workshop-gallery-open', { id: {{ \Illuminate\Support\Js::encode($galleryId) }} })" />
                                                <x-ui.row-action
                                                    label="Download photos"
                                                    icon="fa-solid fa-download"
                                                    tone="neutral"
                                                    href="{{ route('account.workshop-report.photos.download', [...$filterQuery, 'workshop_id' => $workshop->getKey()]) }}"
                                                />
                                            @endif
                                            @if($workshop->hero)
                                                <x-ui.row-action label="Download hero image" icon="fa-solid fa-image" tone="neutral" href="{{ route('account.workshop-report.hero.download', $workshop) }}" />
                                            @endif
                                            @if(filled($workshop->report_promotion_copy))
                                                <x-ui.row-action label="Download promotion text" icon="fa-solid fa-file-arrow-down" tone="neutral" href="{{ route('account.workshop-report.promotion-copy.download', $workshop) }}" />
                                                <x-ui.row-action
                                                    label="Copy promotion text"
                                                    icon="fa-regular fa-copy"
                                                    tone="neutral"
                                                    x-data
                                                    x-on:click.prevent="$SM.copyRichTextToClipboard({{ \Illuminate\Support\Js::encode($workshop->report_promotion_copy_html) }}, {{ \Illuminate\Support\Js::encode($workshop->report_promotion_copy) }})"
                                                />
                                            @endif
                                            @if($workshop->isPubliclyVisible())
                                                <x-ui.row-action label="View workshop" icon="fa-solid fa-arrow-up-right-from-square" tone="neutral" href="{{ route('workshop.show', $workshop) }}" target="_blank" rel="noopener noreferrer" />
                                            @endif
                                        </x-ui.row-actions>
                                    @else
                                        <span class="text-gray-400">—</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </x-slot:body>
                </x-ui.table>

                <div class="mt-5 grid gap-3 sm:flex sm:flex-wrap sm:justify-end">
                    <x-ui.button color="outline" class="w-full sm:w-auto" href="{{ route('account.workshop-report.export', $filterQuery) }}" icon="fa-solid fa-file-csv" label="Download CSV" />
                    <x-ui.button color="outline" class="w-full sm:w-auto" href="{{ route('account.workshop-report.photos.download', $filterQuery) }}" icon="fa-solid fa-images" label="Download All Photos" />
                </div>

                <x-ui.list-pagination :paginator="$workshops" />
            @endif
        </div>
    </x-container>
</x-layout>
