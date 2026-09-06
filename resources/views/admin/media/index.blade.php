<x-layout title="Media">
    <x-mast title="Media" description="Manage your files, photos and shared resources.">
        <x-slot:breadcrumbs><a href="{{ route('admin.dashboard') }}" class="hover:underline">Dashboard</a></x-slot:breadcrumbs>
        <x-slot:actions>
                    <x-ui.page-upload color="mast" id="admin-media-bulk-upload" />
                    <x-ui.action-menu color="mast" id="media-tools" title="Media tools">
                        <a href="{{ route('admin.media.create') }}"><i class="fa-solid fa-file-circle-plus"></i>Create with metadata</a>
                        <a href="{{ route('admin.media.duplicates') }}"><i class="fa-solid fa-clone"></i>Find Duplicates <x-ui.badge color="warning">{{ $duplicateAttentionCount }}</x-ui.badge></a>
                        <x-ui.button variant="plain" id="regenerate-missing-variants-button" onclick="this.closest('dialog').close(); confirmRegenerateMissingVariants()"><i class="fa-solid fa-arrows-rotate"></i>Regenerate Missing Variants</x-ui.button>
                    </x-ui.action-menu>
        </x-slot:actions>
    </x-mast>
    <x-container class="py-5 sm:py-8">
        <div id="regenerate-missing-variants-status" class="hidden mb-4 rounded border border-gray-200 bg-gray-50 px-3 py-2">
            <div class="mb-1 flex items-center justify-between">
                <div class="text-sm font-semibold text-gray-700" id="regenerate-missing-variants-status-title">Regenerating Missing Variants</div>
                <div class="text-xs text-gray-600" id="regenerate-missing-variants-status-percent">0%</div>
            </div>
            <div class="h-2 w-full overflow-hidden rounded bg-gray-200">
                <div id="regenerate-missing-variants-status-bar" class="h-2 rounded bg-primary-color transition-all duration-300" style="width:0"></div>
            </div>
            <div class="mt-1 text-xs text-gray-600" id="regenerate-missing-variants-status-meta"></div>
            <div id="regenerate-missing-variants-status-errors" class="mt-3 hidden">
                <div class="text-xs font-semibold text-red-700">Processing errors</div>
                <ul id="regenerate-missing-variants-status-errors-list" class="mt-1 space-y-1 text-xs"></ul>
            </div>
        </div>
            <x-ui.dynamic-list name="admin-media-index">
                @php
                    $filterLabels = ['search' => 'Search', 'type' => 'Type', 'usage' => 'Usage', 'mime_type' => 'MIME', 'name_pattern' => 'Name matches', 'tags_include' => 'Has all tags', 'tags_exclude' => 'Without tags', 'visibility' => 'Visibility', 'storage_disk' => 'Storage', 'workshop' => 'Workshop', 'location' => 'Location', 'size_min' => 'Min MB', 'size_max' => 'Max MB', 'uploaded_from' => 'From', 'uploaded_to' => 'To', 'user_id' => 'Owner'];
                    $activeFilters = collect(request()->only(array_keys($filterLabels)))->filter(fn ($value) => is_scalar($value) && (string) $value !== '');
                    $presetFilters = ['all' => [], 'images' => ['type' => 'image'], 'unused' => ['usage' => 'unused']];
                    $presetItems = collect(['all' => 'All media', 'images' => 'Images', 'unused' => 'Unused'])->map(fn ($title, $key) => ['title' => $title, 'count' => $presetCounts[$key], 'active' => $activeFilters->all() == $presetFilters[$key], 'route' => route('admin.media.index', array_merge(request()->only(['view', 'sort', 'direction', 'per_page']), $presetFilters[$key]))])->values()->all();
                    $clearUrl = route('admin.media.index', request()->only(['view', 'sort', 'direction', 'per_page']));
                @endphp
                <div data-filter-controls data-filter-schema="{{ json_encode(collect($filterLabels)->map(fn ($label, $key) => ['label' => $label, 'type' => 'text', 'count' => $key !== 'search'])->all()) }}" data-filter-search="search">
                <x-ui.preset-views :items="$presetItems" label="Media presets" />
                <div class="my-5 flex flex-wrap items-center gap-3">
                    <x-ui.button color="outline" data-open-dialog="media-filter-dialog" aria-haspopup="dialog" class="h-11 rounded-lg border-gray-300! shadow-none"><i class="fa-solid fa-filter mr-2"></i>Filters <x-ui.badge data-filter-count color="sky" class="ml-2" :hidden="$activeFilters->except('search')->isEmpty()">{{ $activeFilters->except('search')->count() }}</x-ui.badge></x-ui.button>
                    <x-ui.button color="outline" data-open-dialog="media-sort-dialog" aria-haspopup="dialog" class="h-11 rounded-lg border-gray-300! shadow-none lg:hidden"><i class="fa-solid fa-arrow-down-short-wide mr-2"></i>{{ \App\Services\MediaListFilters::SORTS[request('sort', 'created_at')] }} <span class="ml-1">{{ request('direction', 'desc') === 'asc' ? '↑' : '↓' }}</span></x-ui.button>
                    <nav data-view-tabs aria-label="Media layout" class="ml-auto flex rounded-lg border border-slate-200 bg-white p-1">
                        @foreach(['table' => ['Table view', 'fa-table-list'], 'photos' => ['Photo view', 'fa-table-cells-large']] as $mode => [$label, $icon])
                            <a href="{{ route('admin.media.index', array_merge(request()->except(['page', 'view']), ['view' => $mode])) }}" aria-label="{{ $label }}" @if($view === $mode) aria-current="page" @endif class="flex h-9 w-10 items-center justify-center rounded-md {{ $view === $mode ? 'bg-sky-50 text-primary-color' : 'text-slate-500' }}"><i class="fa-solid {{ $icon }}"></i></a>
                        @endforeach
                    </nav>
                </div>
                    <div data-filter-chips @if($activeFilters->isEmpty()) hidden @endif class="mb-5 flex flex-wrap items-center gap-2">
                        @foreach($activeFilters as $key => $value)
                            <x-ui.filter-chip :label="$filterLabels[$key].': '.($key === 'user_id' && $filteredOwner ? ($filteredOwner->getName() ?: $filteredOwner->email) : $value)" :url="request()->fullUrlWithQuery([$key => null, 'page' => null])" />
                        @endforeach
                        <a data-dynamic-link href="{{ $clearUrl }}" class="ml-auto text-sm text-primary-color underline">Clear filters</a>
                    </div>
                @include('admin.media.partials.filters')
                </div>
                <div data-media-selection data-selecting="false" data-selection-url="{{ route('admin.media.selection', request()->query()) }}" data-total="{{ $media->total() }}" @if(session('admin_media_bulk_clear_selection')) data-clear-selection @endif>
                    @if($media->isEmpty())
                        <x-none-found item="media" :search="request('search')" />
                    @elseif($view === 'table')
                        <x-ui.table variant="listing" class="rounded-xl border border-slate-200 bg-white" caption="Media files">
                            <x-slot:header>
                                <th class="sm-selection-cell"><x-ui.checkbox id="admin-media-select-page" label="Select all media on this page" aria-label="Select all media on this page" labelHidden bare small /></th>
                                <x-ui.sort-heading field="title" label="File" />
                                <th class="hidden xl:table-cell">Owner</th>
                                <x-ui.sort-heading field="mime_type" label="Type" center class="hidden lg:table-cell" />
                                <x-ui.sort-heading field="size" label="Size" center class="hidden sm:table-cell" />
                                <x-ui.sort-heading field="visibility" label="Visibility" center class="hidden lg:table-cell" />
                                <x-ui.sort-heading field="created_at" label="Uploaded" center class="hidden lg:table-cell" />
                                <th class="text-center!"><span class="hidden sm:inline">Actions</span></th>
                            </x-slot:header>
                            <x-slot:body>
                                @foreach($media as $medium)
                                    <tr>
                                        <td class="sm-selection-cell"><x-ui.checkbox :value="$medium->name" :label="'Select '.$medium->title" :aria-label="'Select '.$medium->title" labelHidden bare small class="admin-media-select-item" /></td>
                                        <td><div class="flex min-w-0 items-center gap-3"><img src="{{ $medium->thumbnail }}" alt="" class="h-12 w-12 shrink-0 rounded-lg bg-slate-50 object-contain" @if(in_array($medium->status, ['processing', 'queued'])) data-thumbnail="{{ $medium->name }}" @endif loading="lazy"><div class="min-w-0"><a href="{{ route('admin.media.edit', $medium) }}" class="sm-media-title">{{ $medium->title }}</a><div class="sm-media-filename">{{ $medium->name }}</div><div class="mt-1 flex flex-wrap items-center gap-2 lg:hidden"><x-ui.media-visibility :media="$medium" />@if($medium->is_private && $medium->visibility !== 'public')<x-ui.badge color="slate">Private owner</x-ui.badge>@endif<span class="text-xs text-slate-500 sm:hidden">{{ \App\Helpers::bytesToString($medium->size) }}</span></div></div></div></td>
                                        <td class="hidden xl:table-cell text-sm text-slate-600">{{ $medium->user?->getName() ?: $medium->user?->email ?: 'Unassigned' }}</td>
                                        <td class="hidden lg:table-cell text-center!">{{ $medium->file_type }}</td>
                                        <td class="hidden sm:table-cell text-center!"><x-ui.nonbreaking>{{ \App\Helpers::bytesToString($medium->size) }}</x-ui.nonbreaking></td>
                                        <td class="hidden lg:table-cell text-center!"><x-ui.media-visibility :media="$medium" />@if($medium->is_private && $medium->visibility !== 'public')<x-ui.badge color="slate">Private owner</x-ui.badge>@endif</td>
                                        <td class="hidden lg:table-cell text-center!"><x-ui.date-time>{{ $medium->created_at->format('M j Y, g:i a') }}</x-ui.date-time></td>
                                        <td class="text-center! whitespace-nowrap">@include('admin.media.partials.actions')</td>
                                    </tr>
                                @endforeach
                            </x-slot:body>
                        </x-ui.table>
                    @else
                        <div class="sm-selection-cell mb-3"><x-ui.checkbox id="admin-media-select-page" label="Select all media on this page" aria-label="Select all media on this page" bare small /></div>
                        <div data-list-results class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-5">
                            @foreach($media as $medium)
                                <article class="min-w-0 rounded-xl border border-slate-200 bg-white p-2">
                                    <div class="sm-selection-cell pb-2"><x-ui.checkbox :id="'admin-media-select-photos-'.md5($medium->name)" :value="$medium->name" :label="'Select '.$medium->title" :aria-label="'Select '.$medium->title" labelHidden bare small class="admin-media-select-item" /></div>
                                    <a href="{{ route('admin.media.edit', $medium) }}" class="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-slate-50"><img src="{{ str_starts_with($medium->mime_type, 'image/') ? $medium->url('md', true) : $medium->thumbnail }}" alt="{{ $medium->title }}" class="h-full w-full object-contain" loading="lazy" @if(in_array($medium->status, ['processing', 'queued'])) data-thumbnail="{{ $medium->name }}" @endif></a>
                                    <div class="mt-2 flex items-center gap-2"><div class="min-w-0 flex-1"><a href="{{ route('admin.media.edit', $medium) }}" class="sm-media-title">{{ $medium->title }}</a><div class="text-xs text-slate-500">{{ \App\Helpers::bytesToString($medium->size) }}</div></div>@include('admin.media.partials.actions')</div>
                                </article>
                            @endforeach
                        </div>
                    @endif
                <x-ui.list-pagination :paginator="$media->appends(request()->query())" label="files">
                    <x-slot:actions>
                    <form id="admin-media-bulk-form" method="POST" action="{{ route('admin.media.bulk.select') }}">
                        @csrf
                        <div id="admin-media-bulk-inputs"></div>
                        <div id="admin-media-selection-toolbar" class="sm-list-bulkbar mb-0!" data-selected="false">
                            <x-ui.bulk-edit-button type="submit" id="admin-media-edit-selected" :count="0" data-bulk-selected class="ml-auto px-3 sm:px-8" disabled />
                            <x-ui.button variant="plain" data-toggle-selection class="ml-auto lg:hidden text-primary-color underline">Select</x-ui.button>
                        </div>
                    </form>
                    </x-slot:actions>
                </x-ui.list-pagination>
                </div>
            </x-ui.dynamic-list>
        <x-ui.bulk-editor id="media-bulk-edit-dialog" title="Bulk edit media" loader-id="media-bulk-loader" />
        <x-ui.list-dialog id="media-edit-dialog" title="Edit media details" kind="edit">
            <form data-media-edit-form class="p-5 space-y-4">
                <x-ui.input name="title" label="Title" required class="mb-0"  aria-label="Title" />
                <x-ui.select name="visibility" label="Visibility" class="mb-0" aria-label="Visibility"><option value="private">Private</option><option value="protected">Protected</option><option value="public">Public</option></x-ui.select>
                <x-ui.input name="tags" label="Tags (comma separated)" maxlength="255" class="mb-0"  aria-label="Tags (comma separated)" />
                <x-ui.input type="textarea" name="caption" label="Caption" class="mb-0"  aria-label="Caption" />
                <div class="flex justify-end gap-3"><x-ui.button color="outline" data-close-dialog>Cancel</x-ui.button><x-ui.button type="submit">Save changes</x-ui.button></div>
            </form>
        </x-ui.list-dialog>
    </x-container>
</x-layout>
<script>
    const regenerateMissingVariantsState = {
        startUrl: @json(route('admin.media.regenerate-missing-variants')),
        statusUrl: @json(route('admin.media.regenerate-missing-variants.status')),
        csrf: @json(csrf_token()),
        initial: @json($missingVariantRegeneration ?? ['running' => false]),
        pollTimer: null,
    };

    function regenerateMissingVariantsElements() {
        return {
            button: document.getElementById('regenerate-missing-variants-button'),
            panel: document.getElementById('regenerate-missing-variants-status'),
            percent: document.getElementById('regenerate-missing-variants-status-percent'),
            bar: document.getElementById('regenerate-missing-variants-status-bar'),
            meta: document.getElementById('regenerate-missing-variants-status-meta'),
            title: document.getElementById('regenerate-missing-variants-status-title'),
            errorsWrap: document.getElementById('regenerate-missing-variants-status-errors'),
            errorsList: document.getElementById('regenerate-missing-variants-status-errors-list'),
        };
    }

    function renderRegenerateMissingVariantsErrors(errors) {
        const elements = regenerateMissingVariantsElements();
        if (!elements.errorsWrap || !elements.errorsList) {
            return;
        }

        const items = Array.isArray(errors) ? errors : [];
        elements.errorsList.innerHTML = '';
        if (items.length === 0) {
            elements.errorsWrap.classList.add('hidden');
            return;
        }

        elements.errorsWrap.classList.remove('hidden');
        items.forEach((item) => {
            const li = document.createElement('li');
            li.className = 'rounded border border-red-200 bg-red-50 px-2 py-1';

            const link = document.createElement('a');
            link.href = String(item.edit_url || '#');
            link.className = 'font-semibold text-red-800 hover:underline';
            link.textContent = String(item.title || item.name || 'Media item');
            if (link.href !== '#') {
                link.target = '_blank';
                link.rel = 'noopener noreferrer';
            }

            const message = document.createElement('div');
            message.className = 'mt-0.5 text-red-700 whitespace-pre-line';
            message.textContent = String(item.message || 'Unknown processing error');

            li.appendChild(link);
            li.appendChild(message);
            elements.errorsList.appendChild(li);
        });
    }

    function updateRegenerateMissingVariantsUI(status) {
        const elements = regenerateMissingVariantsElements();
        if (!elements.button || !elements.panel) {
            return;
        }

        const running = !!(status && status.running);
        elements.button.disabled = running;
        elements.button.classList.toggle('opacity-60', running);
        elements.button.classList.toggle('cursor-not-allowed', running);

        if (!status || (!running && !status.finished && !status.cancelled)) {
            elements.panel.classList.add('hidden');
            renderRegenerateMissingVariantsErrors([]);
            return;
        }

        elements.panel.classList.remove('hidden');
        const progress = Number.isFinite(Number(status.progress)) ? Number(status.progress) : 0;
        const processed = Number.isFinite(Number(status.processed_jobs)) ? Number(status.processed_jobs) : 0;
        const total = Number.isFinite(Number(status.total_jobs)) ? Number(status.total_jobs) : 0;
        const pending = Number.isFinite(Number(status.pending_jobs)) ? Number(status.pending_jobs) : 0;
        const failed = Number.isFinite(Number(status.failed_jobs)) ? Number(status.failed_jobs) : 0;
        elements.percent.textContent = `${progress}%`;
        elements.bar.style.width = `${Math.max(0, Math.min(100, progress))}%`;
        renderRegenerateMissingVariantsErrors(status.errors);

        if (running) {
            elements.title.textContent = 'Regenerating Missing Variants';
            elements.meta.textContent = `${processed} of ${total} processed, ${pending} pending${failed > 0 ? `, ${failed} failed` : ''}`;
            return;
        }

        if (status.finished) {
            elements.title.textContent = 'Missing Variant Regeneration Complete';
            elements.meta.textContent = `${processed} of ${total} processed${failed > 0 ? `, ${failed} failed` : ''}`;
            return;
        }

        if (status.cancelled) {
            elements.title.textContent = 'Missing Variant Regeneration Cancelled';
            elements.meta.textContent = `${processed} of ${total} processed${failed > 0 ? `, ${failed} failed` : ''}`;
        }
    }

    async function fetchRegenerateMissingVariantsStatus() {
        const response = await fetch(regenerateMissingVariantsState.statusUrl, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
        });

        if (!response.ok) {
            throw new Error('Failed to load regeneration status');
        }

        const status = await response.json();
        updateRegenerateMissingVariantsUI(status);

        if (!status.running && regenerateMissingVariantsState.pollTimer) {
            clearInterval(regenerateMissingVariantsState.pollTimer);
            regenerateMissingVariantsState.pollTimer = null;
        }
    }

    function ensureRegenerateMissingVariantsPolling() {
        if (regenerateMissingVariantsState.pollTimer) {
            return;
        }

        regenerateMissingVariantsState.pollTimer = setInterval(() => {
            fetchRegenerateMissingVariantsStatus().catch(() => {
                /* empty */
            });
        }, 3000);
    }

    async function startRegenerateMissingVariants() {
        const response = await fetch(regenerateMissingVariantsState.startUrl, {
            method: 'POST',
            headers: {
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': regenerateMissingVariantsState.csrf,
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: JSON.stringify({}),
            credentials: 'same-origin',
        });

        const data = await response.json();
        if (!response.ok || data.ok === false) {
            throw new Error(data.message || 'Could not queue regeneration');
        }

        if (data.regeneration && data.regeneration.running) {
            updateRegenerateMissingVariantsUI(data.regeneration);
            ensureRegenerateMissingVariantsPolling();
            return;
        }

        await fetchRegenerateMissingVariantsStatus();
    }

    document.addEventListener('DOMContentLoaded', () => {
        updateRegenerateMissingVariantsUI(regenerateMissingVariantsState.initial);
        if (regenerateMissingVariantsState.initial && regenerateMissingVariantsState.initial.running) {
            ensureRegenerateMissingVariantsPolling();
        }

        fetchRegenerateMissingVariantsStatus().catch(() => {
            /* empty */
        });
    });

    function confirmRegenerateMissingVariants() {
        const doStart = () => {
            startRegenerateMissingVariants().catch((error) => {
                const message = error && error.message ? error.message : 'Could not queue regeneration';
                if (window.SM && typeof window.SM.notice === 'function') {
                    window.SM.notice('Regeneration failed', message, 'danger');
                }
            });
        };

        if (!window.SM || typeof window.SM.confirm !== 'function') {
            return;
        }

        window.SM.confirm(
            'Regenerate missing variants',
            'Queue regeneration for missing variants only? Existing variants will not be replaced.',
            'Queue Regeneration',
            (isConfirmed) => {
                if (!isConfirmed) {
                    return;
                }
                doStart();
            }
        );
    }
</script>
