<x-layout>
    @php
        $notice = is_array(session('message')) ? session('message') : null;
        $backupMode = (string) ($backup['mode'] ?? '');
        $isIncremental = $backupMode === \App\Services\FileBackupService::MODE_INCREMENTAL;
        $currentPath = trim((string) ($pathPrefix ?? ''), '/');

        $description = 'Created ' . ($backup['created_at'] ?? '-')
            . ' • ' . number_format((int) ($backup['uploaded_files'] ?? 0)) . ' file(s)'
            . (((int) ($backup['deleted_files'] ?? 0)) > 0
                ? ' • ' . number_format((int) $backup['deleted_files']) . ' deleted marker(s)'
                : '')
            . ' • ' . \App\Helpers::bytesToString((int) ($backup['size'] ?? 0));
@endphp

{{--    <div class="text-lg font-bold">{{ ucfirst($backupMode) }} backup</div>--}}

    <x-mast backRoute="admin.server.backups" backTitle="Backups" :description="$description">
        File Backup - {{ $backup['filename'] }}
    </x-mast>

    <x-container>
        @if(isset($notice) && is_array($notice))
            <div class="mb-4 rounded-md border px-3 py-2 text-sm {{ ($notice['type'] ?? '') === 'success' ? 'border-green-200 bg-green-50 text-green-800' : (($notice['type'] ?? '') === 'warning' ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-red-200 bg-red-50 text-red-800') }}">
                {{ (string) ($notice['text'] ?? '') }}
            </div>
        @endif

        <form
            id="file-backup-selection-form"
            method="POST"
            action="{{ route('admin.server.files.restore', ['mode' => $backupMode, 'filename' => $backup['filename']]) }}"
            data-restore-action="{{ route('admin.server.files.restore', ['mode' => $backupMode, 'filename' => $backup['filename']]) }}"
            data-download-action="{{ route('admin.server.files.download-selected', ['mode' => $backupMode, 'filename' => $backup['filename']]) }}"
            data-storage-key="file-backup:{{ $backupMode }}:{{ $backup['filename'] }}"
        >
            @csrf
            <input type="hidden" name="path" value="{{ $currentPath }}">
            <div data-file-backup-hidden-inputs></div>

            <div class="mb-4 rounded-b-lg border border-gray-200 bg-white p-4 shadow-sm">
                @if($isIncremental)
                    <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-900">
                        This is an incremental backup. It contains only files that changed in the backup window plus deleted-path markers. For a whole-tree restore, open the latest full backup instead.
                    </div>
                @endif

                <div class="w-full flex flex-col gap-4 items-center sm:flex-row sm:gap-2">
                    <div class="block flex-1 w-full">
                        <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Search</span>
                        <input type="search" data-file-backup-search placeholder="Search filename or path" class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-primary-color focus:outline-none focus:ring-2 focus:ring-primary-color/20">
                    </div>
                    <div class="flex gap-4 items-center">
                        <div class="block">
                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Filter</span>
                            <div class="relative">
                                <select data-file-backup-filter class="w-full appearance-none rounded-lg border border-gray-300 bg-white px-3 py-2 pr-10 text-sm shadow-sm focus:border-primary-color focus:outline-none focus:ring-2 focus:ring-primary-color/20">
                                    <option value="all">All items</option>
                                    <option value="folders">Folders only</option>
                                    <option value="files">Files only</option>
                                    <option value="missing">Missing on site</option>
                                    <option value="changed">Changed on site</option>
                                    <option value="same">Matches current site</option>
                                </select>
                                <i class="fa-solid fa-chevron-down pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 text-xs text-gray-400"></i>
                            </div>
                        </div>
                        <button type="button" class="mt-4 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 transition hover:border-primary-color hover:text-primary-color" data-file-backup-select-visible>Select visible</button>
                        <button type="button" class="mt-4 rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm text-gray-700 transition hover:border-primary-color hover:text-primary-color" data-file-backup-clear>Select none</button>
                    </div>
                </div>
            </div>

            <div class="m-4 text-sm text-gray-500">
                <div class="mt-1 flex flex-wrap items-center gap-1">
                    <a href="{{ route('admin.server.files.show', ['mode' => $backupMode, 'filename' => $backup['filename']]) }}" class="text-primary-color hover:underline">All files</a>
                    @foreach($breadcrumbs as $crumb)
                        <span class="text-gray-400">/</span>
                        <a href="{{ route('admin.server.files.show', ['mode' => $backupMode, 'filename' => $backup['filename'], 'path' => $crumb['path']]) }}" class="text-primary-color hover:underline">{{ $crumb['label'] }}</a>
                    @endforeach
                </div>
            </div>

            <div class="overflow-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                <table class="sm-backup-table w-full table-fixed text-sm">
                    <thead class="bg-gray-50">
                    <tr>
                        <th class="w-10 px-3 py-2"></th>
                        <th class="px-3 py-2 text-left">
                            <button type="button" class="inline-flex items-center gap-1 font-semibold uppercase tracking-wide text-gray-600 transition hover:text-primary-color" data-file-backup-sort-button data-file-backup-sort-field="name">
                                <span>Name</span>
                                <i class="fa-solid fa-sort text-[11px] text-gray-400" data-file-backup-sort-indicator="name"></i>
                            </button>
                        </th>
                        <th class="w-28 px-3 py-2 text-right">
                            <button type="button" class="ml-auto inline-flex items-center gap-1 font-semibold uppercase tracking-wide text-gray-600 transition hover:text-primary-color" data-file-backup-sort-button data-file-backup-sort-field="size">
                                <span>Size</span>
                                <i class="fa-solid fa-sort text-[11px] text-gray-400" data-file-backup-sort-indicator="size"></i>
                            </button>
                        </th>
                        <th class="w-40 px-3 py-2 text-left">
                            <button type="button" class="inline-flex items-center gap-1 font-semibold uppercase tracking-wide text-gray-600 transition hover:text-primary-color" data-file-backup-sort-button data-file-backup-sort-field="modified">
                                <span>Modified</span>
                                <i class="fa-solid fa-sort text-[11px] text-gray-400" data-file-backup-sort-indicator="modified"></i>
                            </button>
                        </th>
                        <th class="w-24 px-3 py-2 text-center">Action</th>
                    </tr>
                    </thead>
                    <tbody data-file-backup-tbody>
                    @forelse($entries as $entry)
                        @php
                            $entryType = (string) ($entry['type'] ?? 'file');
                            $entryKey = $entryType === 'folder' ? ((string) $entry['path'] . '/') : (string) $entry['path'];
                            $isMediaGroup = $entryType === 'media_group';
                            $groupChildren = is_array($entry['group_children'] ?? null) ? $entry['group_children'] : [];
                            $stateData = $isMediaGroup ? ($entry['group_state'] ?? []) : ($entry['current_state'] ?? []);
                            $stateLabel = (string) ($stateData['label'] ?? '');
                            $stateTone = (string) ($stateData['tone'] ?? '');
                            $rowState = (string) ($stateData['state'] ?? '');
                            $searchText = strtolower(trim((string) ($entry['search_text'] ?? trim((string) ($entry['name'] ?? '') . ' ' . (string) ($entry['path'] ?? '') . ' ' . (string) ($stateLabel ?? '')))));
                        @endphp
                        <tr
                            class="border-t border-gray-100"
                            data-file-backup-row
                            data-file-backup-row-key="{{ $entryKey }}"
                            data-file-backup-row-name="{{ strtolower((string) ($entry['name'] ?? '')) }}"
                            data-file-backup-row-size="{{ (int) ($entry['size'] ?? 0) }}"
                            data-file-backup-row-modified="{{ (int) ($entry['last_modified'] ?? 0) }}"
                            data-file-backup-row-type="{{ $entryType }}"
                            data-file-backup-row-state="{{ $isMediaGroup ? ($rowState !== '' ? $rowState : 'same') : ((string) (($entry['current_state']['state'] ?? '') ?: ($entryType === 'folder' ? 'folder' : ($isIncremental ? 'changed' : 'same')))) }}"
                            data-file-backup-row-search="{{ $searchText }}"
                            data-file-backup-group-id="{{ (string) ($entry['group_id'] ?? '') }}"
                        >
                            <td class="px-3 py-2 align-top" data-label="Select">
                                <input
                                    type="checkbox"
                                    value="{{ $entryKey }}"
                                    class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                    data-file-backup-item
                                    data-file-backup-key="{{ $entryKey }}"
                                    data-file-backup-label="{{ $entryKey }}"
                                    data-file-backup-type="{{ $entryType }}"
                                    data-file-backup-state="{{ $rowState }}"
                                    data-file-backup-name="{{ $entry['name'] }}"
                                    data-file-backup-size="{{ (int) ($entry['size'] ?? 0) }}"
                                    data-file-backup-modified="{{ (int) ($entry['last_modified'] ?? 0) }}"
                                >
                            </td>
                            <td class="px-3 py-2 align-top" data-label="Name">
                                <div class="flex min-w-0 items-start gap-2">
                                    <i class="fa-solid {{ $entryType === 'folder' ? 'fa-folder text-amber-600' : ($isMediaGroup ? 'fa-photo-film text-slate-500' : 'fa-file text-slate-500') }} mt-0.5"></i>
                                    <div class="min-w-0">
                                        @if($entryType === 'folder')
                                            <a href="{{ route('admin.server.files.show', ['mode' => $backupMode, 'filename' => $backup['filename'], 'path' => $entry['path']]) }}" class="block max-w-full whitespace-normal break-words font-medium text-primary-color hover:underline md:truncate" title="{{ $entry['path'] }}">
                                                {{ $entry['name'] }}
                                            </a>
                                            <div class="mt-1 max-w-full whitespace-normal break-words text-xs text-gray-500 md:truncate" title="{{ $entry['path'] }}">
                                                {{ $entry['path'] }}
                                            </div>
                                        @else
                                            <button
                                                type="button"
                                                class="block max-w-full whitespace-normal break-words font-medium text-left text-primary-color hover:underline md:truncate"
                                                data-file-backup-toggle
                                                data-file-backup-toggle-key="{{ $entryKey }}"
                                                title="{{ $entryKey }}"
                                            >
                                                {{ $entry['name'] }}
                                            </button>
                                            @if($isMediaGroup)
                                                <div class="mt-1 max-w-full whitespace-normal break-words text-xs font-medium {{ $stateTone === 'danger' ? 'text-rose-700' : ($stateTone === 'warning' ? 'text-amber-700' : 'text-gray-500') }} md:truncate" title="{{ $entryKey }}">
                                                    {{ $stateLabel !== '' ? $stateLabel : '' }}
                                                </div>
                                                <div class="mt-1 max-w-full whitespace-normal break-words text-xs text-gray-500 md:truncate" title="{{ $entryKey }}">
                                                    {{ $entry['group_summary'] ?? '' }}
                                                </div>
                                            @elseif(is_array($entry['current_state'] ?? null) && ($entry['current_state']['state'] ?? '') !== 'same')
                                                <div class="mt-1 max-w-full whitespace-normal break-words text-xs font-medium {{ ($entry['current_state']['tone'] ?? '') === 'danger' ? 'text-rose-700' : 'text-amber-700' }} md:truncate" title="{{ $entryKey }}">
                                                    {{ (string) ($entry['current_state']['label'] ?? '') }}
                                                </div>
                                            @endif
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2 text-right whitespace-nowrap" data-label="Size">
                                {{ \App\Helpers::bytesToString((int) ($entry['size'] ?? 0)) }}
                            </td>
                            <td class="px-3 py-2 whitespace-nowrap" data-label="Modified">
                                @if(($entry['last_modified'] ?? 0) > 0)
                                    {{ \Carbon\Carbon::createFromTimestamp((int) $entry['last_modified'])->format('Y-m-d H:i:s') }}
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-3 py-2 text-center" data-label="Action">
                                <div class="flex items-center justify-center gap-3">
                                    @if($entryType === 'folder')
                                        <a href="{{ route('admin.server.files.show', ['mode' => $backupMode, 'filename' => $backup['filename'], 'path' => $entry['path']]) }}" class="hover:text-primary-color" title="Open folder">
                                            <i class="fa-solid fa-folder-open"></i>
                                        </a>
                                    @elseif($isMediaGroup)
                                        <a href="{{ route('admin.server.files.download', ['mode' => $backupMode, 'filename' => $backup['filename'], 'path' => $entry['path']]) }}" class="hover:text-primary-color" title="Download from backup">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <button type="button" class="hover:text-primary-color" title="Show variants" data-file-backup-group-toggle data-file-backup-group-target="{{ $entry['path'] }}" aria-expanded="false">
                                            <i class="fa-solid fa-chevron-down"></i>
                                        </button>
                                    @else
                                        <a href="{{ route('admin.server.files.download', ['mode' => $backupMode, 'filename' => $backup['filename'], 'path' => $entry['path']]) }}" class="hover:text-primary-color" title="Download from backup">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>

                        @if($isMediaGroup)
                            <tr class="hidden border-t border-gray-100 bg-slate-50" data-file-backup-group-detail data-file-backup-group-id="{{ (string) ($entry['group_id'] ?? $entry['path']) }}" data-expanded="false">
                                <td colspan="5" class="px-3 py-3">
                                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                                        <div class="mb-3 flex items-center justify-between gap-3">
                                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Variants</div>
                                            <div class="text-xs text-gray-500">{{ number_format(count($groupChildren)) }} item(s)</div>
                                        </div>
                                        <div class="grid gap-2 xl:grid-cols-2 2xl:grid-cols-3">
                                            @foreach($groupChildren as $child)
                                                @php
                                                    $childKey = (string) ($child['path'] ?? '');
                                                    $childStateData = (array) ($child['current_state'] ?? []);
                                                    $childStateLabel = (string) ($childStateData['label'] ?? '');
                                                    $childStateTone = (string) ($childStateData['tone'] ?? '');
                                                    $childState = (string) ($childStateData['state'] ?? '');
                                                @endphp
                                                <div class="flex flex-wrap items-start justify-between gap-3 rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                                                    <label class="flex items-start gap-3">
                                                        <input
                                                            type="checkbox"
                                                            value="{{ $childKey }}"
                                                            class="mt-1 h-4 w-4 rounded border-gray-300 text-primary-color focus:ring-primary-color"
                                                            data-file-backup-item
                                                            data-file-backup-key="{{ $childKey }}"
                                                            data-file-backup-label="{{ $childKey }}"
                                                            data-file-backup-type="file"
                                                            data-file-backup-state="{{ $childState }}"
                                                            data-file-backup-name="{{ $child['name'] }}"
                                                            data-file-backup-size="{{ (int) ($child['size'] ?? 0) }}"
                                                            data-file-backup-modified="{{ (int) ($child['last_modified'] ?? 0) }}"
                                                        >
                                                        <span class="mt-0.5 text-slate-500"><i class="fa-solid fa-file"></i></span>
                                                        <span class="min-w-0">
                                                            <span class="block max-w-full whitespace-normal break-words font-medium text-gray-900 md:truncate" title="{{ $childKey }}">{{ $child['name'] }}</span>
                                                            @if($childState !== 'same' && $childStateLabel !== '')
                                                                <span class="mt-1 block max-w-full whitespace-normal break-words text-xs font-medium {{ $childStateTone === 'danger' ? 'text-rose-700' : 'text-amber-700' }} md:truncate" title="{{ $childKey }}">
                                                                    {{ $childStateLabel }}
                                                                </span>
                                                            @endif
                                                        </span>
                                                    </label>
                                                    <div class="flex items-center gap-3 whitespace-nowrap text-xs text-gray-500">
                                                        <span>{{ \App\Helpers::bytesToString((int) ($child['size'] ?? 0)) }}</span>
                                                        <span>
                                                            @if(($child['last_modified'] ?? 0) > 0)
                                                                {{ \Carbon\Carbon::createFromTimestamp((int) $child['last_modified'])->format('Y-m-d H:i:s') }}
                                                            @else
                                                                -
                                                            @endif
                                                        </span>
                                                        <a href="{{ route('admin.server.files.download', ['mode' => $backupMode, 'filename' => $backup['filename'], 'path' => $child['path']]) }}" class="hover:text-primary-color" title="Download from backup">
                                                            <i class="fa-solid fa-download"></i>
                                                        </a>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="5" class="px-3 py-6 text-center text-sm text-gray-500">No files in this folder.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            @if(collect($deletedEntries ?? [])->isNotEmpty())
            <div class="mt-6">
                <h3 class="mb-2 text-sm font-semibold uppercase tracking-wide text-gray-500">Deleted in this incremental run</h3>
                <div class="overflow-auto rounded-lg border border-gray-200 bg-white shadow-sm">
                        <table class="sm-backup-table w-full text-sm">
                            <tbody>
                            @foreach($deletedEntries as $entry)
                                <tr class="border-t border-gray-100">
                                    <td class="px-3 py-2 font-mono text-xs break-all" data-label="Path">{{ $entry['path'] }}</td>
                                    <td class="px-3 py-2 text-right" data-label="State">
                                        <x-ui.badge color="danger">Deleted</x-ui.badge>
                                    </td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endif

            <details open data-file-backup-selection-details class="mt-6 rounded-xl border border-gray-200 bg-slate-50 p-4 shadow-sm">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-gray-800 [&::-webkit-details-marker]:hidden">
                    <span>Selection</span>
                    <span class="inline-flex items-center gap-2 text-gray-500">
                        <span class="font-semibold text-gray-900" data-file-backup-selected-count>0</span>
                        <span>selected</span>
                        <i class="fa-solid fa-chevron-down text-xs"></i>
                    </span>
                </summary>

                <div class="mt-4 space-y-4">
                    <div class="flex flex-wrap items-center gap-2">
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50" data-file-backup-clear-all>Clear all</button>
                        <button type="button" class="rounded-md border border-gray-900 bg-gray-900 px-3 py-1.5 text-sm font-medium text-white transition hover:bg-gray-800 disabled:cursor-not-allowed disabled:border-gray-300 disabled:bg-gray-200 disabled:text-gray-500" data-file-backup-restore disabled>
                            Restore Selected
                        </button>
                        <button type="button" class="rounded-md border border-gray-300 bg-white px-3 py-1.5 text-sm font-medium text-gray-700 transition hover:bg-gray-50 disabled:cursor-not-allowed disabled:bg-gray-100 disabled:text-gray-400" data-file-backup-download disabled>
                            Download ZIP
                        </button>
                    </div>

                    <div class="rounded-lg border border-gray-200 bg-white p-3">
                        <div class="text-sm text-gray-500" data-file-backup-selected-empty>No files selected.</div>
                        <div class="flex flex-col gap-2 w-full" data-file-backup-selected-list></div>
                    </div>
                </div>
            </details>
        </form>
    </x-container>
</x-layout>

<script>
    (() => {
        const form = document.getElementById('file-backup-selection-form');
        const selectVisibleButton = document.querySelector('[data-file-backup-select-visible]');
        const clearButton = document.querySelector('[data-file-backup-clear]');
        const clearAllButton = document.querySelector('[data-file-backup-clear-all]');
        const restoreButton = document.querySelector('[data-file-backup-restore]');
        const downloadButton = document.querySelector('[data-file-backup-download]');
        const selectedCount = document.querySelector('[data-file-backup-selected-count]');
        const selectedEmpty = document.querySelector('[data-file-backup-selected-empty]');
        const selectedList = document.querySelector('[data-file-backup-selected-list]');
        const hiddenInputs = document.querySelector('[data-file-backup-hidden-inputs]');
        const searchInput = document.querySelector('[data-file-backup-search]');
        const filterSelect = document.querySelector('[data-file-backup-filter]');
        const sortButtons = Array.from(document.querySelectorAll('[data-file-backup-sort-button]'));
        const tbody = document.querySelector('[data-file-backup-tbody]');
        const selectionDetails = document.querySelector('[data-file-backup-selection-details]');
        const backupRestoreAction = form instanceof HTMLFormElement ? form.dataset.restoreAction || '' : '';
        const backupDownloadAction = form instanceof HTMLFormElement ? form.dataset.downloadAction || '' : '';
        const storageKey = form instanceof HTMLFormElement ? form.dataset.storageKey || '' : '';
        const uiStorageKey = storageKey !== '' ? `${storageKey}:ui` : '';

        const checkboxes = () => Array.from(document.querySelectorAll('[data-file-backup-item]'));
        const topRows = () => Array.from(document.querySelectorAll('[data-file-backup-row]'));

        const readJson = (key, fallback) => {
            if (key === '' || !window.localStorage) {
                return fallback;
            }

            try {
                const raw = window.localStorage.getItem(key);
                if (!raw) {
                    return fallback;
                }

                const decoded = JSON.parse(raw);
                return decoded && typeof decoded === 'object' ? decoded : fallback;
            } catch (error) {
                return fallback;
            }
        };

        const writeJson = (key, value) => {
            if (key === '' || !window.localStorage) {
                return;
            }

            window.localStorage.setItem(key, JSON.stringify(value));
        };

        const defaultUiState = {
            search: '',
            filter: 'all',
            sortField: 'name',
            sortDirection: 'asc',
        };

        const selectedStore = readJson(storageKey, {});
        const rawUiState = Object.assign({}, defaultUiState, readJson(uiStorageKey, {}));
        const uiState = Object.assign({}, defaultUiState, rawUiState);

        if (typeof rawUiState.sort === 'string' && rawUiState.sort.includes('-')) {
            const [legacyField, legacyDirection] = rawUiState.sort.split('-', 2);
            uiState.sortField = legacyField || uiState.sortField;
            uiState.sortDirection = legacyDirection === 'desc' ? 'desc' : 'asc';
        }

        const selectionEntries = () => Object.values(selectedStore).sort((a, b) => {
            const aLabel = String(a?.label || a?.key || '');
            const bLabel = String(b?.label || b?.key || '');
            return aLabel.localeCompare(bLabel);
        });

        const persistSelection = () => {
            writeJson(storageKey, selectedStore);
        };

        const persistUiState = () => {
            writeJson(uiStorageKey, uiState);
        };

        const itemKeyFromCheckbox = (checkbox) => checkbox?.dataset?.fileBackupKey || checkbox?.value || '';
        const itemLabelFromCheckbox = (checkbox) => checkbox?.dataset?.fileBackupLabel || checkbox?.value || '';
        const itemTypeFromCheckbox = (checkbox) => checkbox?.dataset?.fileBackupType || 'file';
        const itemStateFromCheckbox = (checkbox) => checkbox?.dataset?.fileBackupState || '';

        const isCheckboxVisible = (checkbox) => {
            const detailRow = checkbox.closest('[data-file-backup-group-detail]');
            if (detailRow instanceof HTMLElement) {
                return ! detailRow.classList.contains('hidden');
            }

            const row = checkbox.closest('[data-file-backup-row]');
            if (row instanceof HTMLElement) {
                return ! row.hidden;
            }

            return true;
        };

        const syncVisibleCheckboxes = () => {
            checkboxes().forEach((checkbox) => {
                const key = itemKeyFromCheckbox(checkbox);
                checkbox.checked = key !== '' && Boolean(selectedStore[key]);
            });
        };

        const syncSelectionInputs = () => {
            if (!(hiddenInputs instanceof HTMLElement)) {
                return;
            }

            hiddenInputs.innerHTML = '';
            selectionEntries().forEach((entry) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_items[]';
                input.value = String(entry.key || '');
                hiddenInputs.appendChild(input);
            });
        };

        const syncSelectionDetailsState = () => {
            if (!(selectionDetails instanceof HTMLDetailsElement)) {
                return;
            }

            if (selectionEntries().length > 0) {
                selectionDetails.open = true;
            }
        };

        const renderSelectionSummary = () => {
            if (!(selectedCount instanceof HTMLElement) || !(selectedEmpty instanceof HTMLElement) || !(selectedList instanceof HTMLElement)) {
                return;
            }

            const items = selectionEntries();
            selectedCount.textContent = String(items.length);
            selectedEmpty.classList.toggle('hidden', items.length > 0);

            selectedList.innerHTML = '';
            items.forEach((entry) => {
                const badge = document.createElement('div');
                badge.className = 'flex items-center gap-2 px-2.5 py-1 text-xs font-medium text-gray-700 flex-wrap';

                if (entry.state === 'missing') {
                    badge.className = 'flex items-center gap-2 px-2.5 py-1 text-xs font-medium text-rose-700 flex-wrap';
                } else if (entry.state === 'different') {
                    badge.className = 'flex items-center gap-2 px-2.5 py-1 text-xs font-medium text-amber-800 flex-wrap';
                }

                const icon = document.createElement('i');
                icon.className = 'fa-solid shrink-0 ' + (entry.type === 'folder' ? 'fa-folder text-amber-600' : 'fa-file text-slate-500');

                const text = document.createElement('span');
                text.className = 'font-mono flex-1 min-w-0 break-words';
                text.textContent = String(entry.label || entry.key || '');

                const remove = document.createElement('button');
                remove.type = 'button';
                remove.className = 'ml-auto shrink-0 inline-flex items-center text-gray-500 transition hover:text-gray-900';
                remove.setAttribute('aria-label', 'Delete selection');
                remove.title = 'Delete';
                remove.innerHTML = '<i class="fa-solid fa-trash-can text-[11px]"></i>';
                remove.addEventListener('click', () => removeSelectedItem(String(entry.key || '')));

                badge.appendChild(icon);
                badge.appendChild(text);
                badge.appendChild(remove);
                selectedList.appendChild(badge);
            });
        };

        const updateActionState = () => {
            const hasSelected = selectionEntries().length > 0;
            if (restoreButton instanceof HTMLButtonElement) {
                restoreButton.disabled = ! hasSelected;
            }

            if (downloadButton instanceof HTMLButtonElement) {
                downloadButton.disabled = ! hasSelected;
            }
        };

        const upsertSelectedItem = (checkbox) => {
            const key = itemKeyFromCheckbox(checkbox);
            if (key === '') {
                return;
            }

            if (checkbox.checked) {
                selectedStore[key] = {
                    key,
                    label: itemLabelFromCheckbox(checkbox),
                    type: itemTypeFromCheckbox(checkbox),
                    state: itemStateFromCheckbox(checkbox),
                };
            } else {
                delete selectedStore[key];
            }

            persistSelection();
            syncSelectionInputs();
            renderSelectionSummary();
            syncVisibleCheckboxes();
            syncSelectionDetailsState();
            updateActionState();
        };

        const removeSelectedItem = (key) => {
            delete selectedStore[key];
            persistSelection();
            syncSelectionInputs();
            renderSelectionSummary();
            syncVisibleCheckboxes();
            syncSelectionDetailsState();
            updateActionState();
        };

        const clearSelectedItems = (onlyVisible = false) => {
            if (onlyVisible) {
                checkboxes().forEach((checkbox) => {
                    if (! isCheckboxVisible(checkbox)) {
                        return;
                    }

                    const key = itemKeyFromCheckbox(checkbox);
                    if (key !== '' && selectedStore[key]) {
                        delete selectedStore[key];
                    }
                    checkbox.checked = false;
                });
            } else {
                Object.keys(selectedStore).forEach((key) => {
                    delete selectedStore[key];
                });
                checkboxes().forEach((checkbox) => {
                    checkbox.checked = false;
                });
            }

            persistSelection();
            syncSelectionInputs();
            renderSelectionSummary();
            syncVisibleCheckboxes();
            syncSelectionDetailsState();
            updateActionState();
        };

        const rowMatches = (row) => {
            const query = String(uiState.search || '').trim().toLowerCase();
            const filter = String(uiState.filter || 'all');
            const rowType = String(row.dataset.fileBackupRowType || '');
            const rowState = String(row.dataset.fileBackupRowState || '');
            const haystack = String(row.dataset.fileBackupRowSearch || '');

            if (query !== '' && !haystack.includes(query)) {
                return false;
            }

            if (filter === 'folders' && rowType !== 'folder') {
                return false;
            }

            if (filter === 'files' && rowType !== 'file' && rowType !== 'media_group') {
                return false;
            }

            if (filter === 'missing' && rowState !== 'missing') {
                return false;
            }

            if (filter === 'changed' && rowState !== 'different') {
                return false;
            }

            if (filter === 'same' && rowType !== 'folder' && rowState !== 'same') {
                return false;
            }

            return true;
        };

        const sortRows = (list) => {
            const sortField = String(uiState.sortField || 'name');
            const sortDirection = String(uiState.sortDirection || 'asc') === 'desc' ? -1 : 1;
            const getNumeric = (row, key) => Number(row.dataset[key] || 0);
            const getText = (row, key) => String(row.dataset[key] || '');

            return list.sort((a, b) => {
                const aType = getText(a, 'fileBackupRowType');
                const bType = getText(b, 'fileBackupRowType');
                if (aType !== bType) {
                    return aType === 'folder' ? -1 : 1;
                }

                if (sortField === 'size') {
                    return (getNumeric(a, 'fileBackupRowSize') - getNumeric(b, 'fileBackupRowSize')) * sortDirection;
                }

                if (sortField === 'modified') {
                    return (getNumeric(a, 'fileBackupRowModified') - getNumeric(b, 'fileBackupRowModified')) * sortDirection;
                }

                return getText(a, 'fileBackupRowName').localeCompare(getText(b, 'fileBackupRowName')) * sortDirection;
            });
        };

        const detailRowFor = (row) => {
            const groupId = String(row.dataset.fileBackupGroupId || '');
            if (groupId === '') {
                return null;
            }

            const nextRow = row.nextElementSibling;
            if (nextRow instanceof HTMLElement && nextRow.matches('[data-file-backup-group-detail]') && String(nextRow.dataset.fileBackupGroupId || '') === groupId) {
                return nextRow;
            }

            return null;
        };

        const setGroupExpanded = (row, expanded) => {
            const detailRow = detailRowFor(row);
            if (!(detailRow instanceof HTMLElement)) {
                return;
            }

            detailRow.classList.toggle('hidden', !expanded);
            detailRow.dataset.expanded = expanded ? 'true' : 'false';

            const toggleButton = row.querySelector('[data-file-backup-group-toggle]');
            if (toggleButton instanceof HTMLButtonElement) {
                toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
                const icon = toggleButton.querySelector('i');
                if (icon instanceof HTMLElement) {
                    icon.className = expanded ? 'fa-solid fa-chevron-up' : 'fa-solid fa-chevron-down';
                }
            }
        };

        const updateSortIndicators = () => {
            sortButtons.forEach((button) => {
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                const field = String(button.dataset.fileBackupSortField || '');
                const indicator = button.querySelector('[data-file-backup-sort-indicator]');
                const active = field !== '' && field === String(uiState.sortField || 'name');
                const iconClass = !active
                    ? 'fa-solid fa-sort text-[11px] text-gray-400'
                    : (String(uiState.sortDirection || 'asc') === 'desc'
                        ? 'fa-solid fa-sort-down text-[11px] text-primary-color'
                        : 'fa-solid fa-sort-up text-[11px] text-primary-color');

                if (indicator instanceof HTMLElement) {
                    indicator.className = iconClass;
                }

                button.setAttribute('aria-sort', active ? (String(uiState.sortDirection || 'asc') === 'desc' ? 'descending' : 'ascending') : 'none');
                button.classList.toggle('text-primary-color', active);
            });
        };

        const applyFiltersAndSort = () => {
            if (!(tbody instanceof HTMLElement)) {
                return;
            }

            const list = sortRows(topRows().slice());
            list.forEach((row) => {
                const visible = rowMatches(row);
                row.hidden = !visible;
                tbody.appendChild(row);

                const detailRow = detailRowFor(row);
                if (detailRow instanceof HTMLElement) {
                    detailRow.classList.toggle('hidden', !visible || detailRow.dataset.expanded !== 'true');
                    tbody.appendChild(detailRow);
                }
            });

            updateSortIndicators();
        };

        const bindControls = () => {
            if (searchInput instanceof HTMLInputElement) {
                searchInput.value = String(uiState.search || '');
                searchInput.addEventListener('input', () => {
                    uiState.search = searchInput.value;
                    persistUiState();
                    applyFiltersAndSort();
                });
            }

            if (filterSelect instanceof HTMLSelectElement) {
                filterSelect.value = String(uiState.filter || 'all');
                filterSelect.addEventListener('change', () => {
                    uiState.filter = filterSelect.value;
                    persistUiState();
                    applyFiltersAndSort();
                });
            }

            sortButtons.forEach((button) => {
                if (!(button instanceof HTMLButtonElement)) {
                    return;
                }

                button.addEventListener('click', () => {
                    const field = String(button.dataset.fileBackupSortField || 'name');
                    if (uiState.sortField === field) {
                        uiState.sortDirection = String(uiState.sortDirection || 'asc') === 'asc' ? 'desc' : 'asc';
                    } else {
                        uiState.sortField = field;
                        uiState.sortDirection = 'asc';
                    }

                    persistUiState();
                    applyFiltersAndSort();
                });
            });
        };

        const submitWithAction = (action) => {
            if (!(form instanceof HTMLFormElement) || action === '') {
                return;
            }

            const previousAction = form.action;
            form.action = action;
            form.requestSubmit();
            form.action = previousAction;
        };

        if (selectVisibleButton instanceof HTMLButtonElement) {
            selectVisibleButton.addEventListener('click', () => {
                checkboxes().forEach((checkbox) => {
                    if (!(checkbox instanceof HTMLInputElement) || !isCheckboxVisible(checkbox)) {
                        return;
                    }

                    checkbox.checked = true;
                    upsertSelectedItem(checkbox);
                });
            });
        }

        if (clearButton instanceof HTMLButtonElement) {
            clearButton.addEventListener('click', () => {
                checkboxes().forEach((checkbox) => {
                    if (!(checkbox instanceof HTMLInputElement) || !isCheckboxVisible(checkbox)) {
                        return;
                    }

                    checkbox.checked = false;
                    upsertSelectedItem(checkbox);
                });
            });
        }

        if (clearAllButton instanceof HTMLButtonElement) {
            clearAllButton.addEventListener('click', () => {
                clearSelectedItems(false);
            });
        }

        checkboxes().forEach((checkbox) => {
            if (!(checkbox instanceof HTMLInputElement)) {
                return;
            }

            checkbox.addEventListener('change', () => upsertSelectedItem(checkbox));
        });

        document.querySelectorAll('[data-file-backup-toggle]').forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', () => {
                const row = button.closest('tr');
                const checkbox = row ? row.querySelector('[data-file-backup-item]') : null;
                if (!(checkbox instanceof HTMLInputElement)) {
                    return;
                }

                checkbox.checked = !checkbox.checked;
                upsertSelectedItem(checkbox);
            });
        });

        document.querySelectorAll('[data-file-backup-group-toggle]').forEach((button) => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            button.addEventListener('click', () => {
                const target = String(button.dataset.fileBackupGroupTarget || '');
                const row = topRows().find((candidate) => String(candidate.dataset.fileBackupRowKey || '') === target) || null;
                if (!(row instanceof HTMLElement)) {
                    return;
                }

                const detailRow = detailRowFor(row);
                if (!(detailRow instanceof HTMLElement)) {
                    return;
                }

                setGroupExpanded(row, detailRow.classList.contains('hidden'));
            });
        });

        if (restoreButton instanceof HTMLButtonElement) {
            restoreButton.addEventListener('click', () => {
                if (!(form instanceof HTMLFormElement)) {
                    return;
                }

                const message = 'Restore the selected file(s) from this backup run? This will overwrite matching files on the live disks.';
                if (window.SM && typeof window.SM.confirm === 'function') {
                    window.SM.confirm('Confirm action', message, 'Restore Selected', (isConfirmed) => {
                        if (! isConfirmed) {
                            return;
                        }

                        submitWithAction(backupRestoreAction);
                    });
                    return;
                }

                submitWithAction(backupRestoreAction);
            });
        }

        if (downloadButton instanceof HTMLButtonElement) {
            downloadButton.addEventListener('click', () => {
                submitWithAction(backupDownloadAction);
            });
        }

        bindControls();
        syncVisibleCheckboxes();
        syncSelectionInputs();
        renderSelectionSummary();
        syncSelectionDetailsState();
        updateActionState();
        applyFiltersAndSort();
    })();
</script>
