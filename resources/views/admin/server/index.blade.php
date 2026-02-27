<x-layout>
    <x-mast>Server Info</x-mast>

    <x-container>
        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">Deployment</h3>
                <form method="POST" action="{{ route('admin.server.deploy') }}" data-sm-confirm="Run website updater with selected options? You may need to refresh the page afterward the update completes." data-sm-confirm-button="Run Update" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <x-ui.checkbox
                        name="current"
                        value="1"
                        label="Dev"
                        :noWrapper="true"
                        :inline="true"
                        labelClass="text-sm pt-0" />
                    <x-ui.checkbox
                        name="force"
                        value="1"
                        label="Force"
                        :noWrapper="true"
                        :inline="true"
                        labelClass="text-sm pt-0" />
                    <x-ui.button type="submit" color="dark">Run Update</x-ui.button>
                </form>
            </div>
            <div class="text-xs text-gray-600 mb-3">
                <p><strong>Output Log:</strong> <code>{{ $deployOutputPath }}</code></p>
                <p><strong>Last Output Update:</strong> <span id="deploy-log-updated">{{ $deployOutputModifiedAt ?? 'N/A' }}</span></p>
                <p><strong>Showing:</strong> Last 150 lines</p>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <form method="POST" action="{{ route('admin.server.deploy.log.clear') }}" data-sm-confirm="Clear deploy output log? This cannot be undone." data-sm-confirm-button="Clear Log">
                        @csrf
                        <x-ui.button type="submit" color="danger">Clear Log</x-ui.button>
                    </form>
                    <button type="button" id="deploy-log-refresh" class="whitespace-nowrap text-center justify-center rounded-md px-4 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition hover:bg-gray-500 focus-visible:outline-primary-color text-gray-800 border border-gray-400 bg-white hover:text-white">Refresh Log</button>
                    <x-ui.checkbox
                        id="deploy-log-auto-refresh"
                        label="Auto-refresh every 10 seconds"
                        :checked="true"
                        :noWrapper="true"
                        :inline="true"
                        labelClass="text-sm pt-0" />
                </div>
            </div>
            @if(!$deployOutputExists)
            <p id="deploy-log-empty" class="text-sm text-gray-600">Log file is empty.</p>
            <pre id="deploy-log-content" class="hidden text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[20rem] whitespace-pre-wrap"></pre>
            @elseif(trim($deployOutputContent) === '')
            <p id="deploy-log-empty" class="text-sm text-gray-600">Deploy output log is empty.</p>
            <pre id="deploy-log-content" class="hidden text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[20rem] whitespace-pre-wrap"></pre>
            @else
            <p id="deploy-log-empty" class="hidden text-sm text-gray-600">Deploy output log is empty.</p>
            <pre id="deploy-log-content" class="text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[20rem] whitespace-pre-wrap">{{ $deployOutputContent }}</pre>
            @endif
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">Database Backup</h3>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.server.database.backup-now') }}" data-sm-confirm="Create a full database backup now?" data-sm-confirm-button="Backup Now">
                        @csrf
                        <x-ui.button type="submit" color="dark">Backup Now</x-ui.button>
                    </form>
{{--                    <form method="POST" action="{{ route('admin.server.database.export') }}" data-sm-confirm="Create and download a full database backup now?" data-sm-confirm-button="Export Backup">--}}
{{--                        @csrf--}}
{{--                        <x-ui.button type="submit" color="outline">Export (.sql.gz)</x-ui.button>--}}
{{--                    </form>--}}
                </div>
            </div>

            @if(is_array(session('database_backup_notice')))
                @php($dbNotice = session('database_backup_notice'))
                <div class="mb-3 rounded-md border px-3 py-2 text-sm {{ ($dbNotice['type'] ?? '') === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
                    {{ (string) ($dbNotice['text'] ?? '') }}
                </div>
            @endif

            <p class="text-xs text-gray-600 mb-3">Hourly backups are scheduled via Laravel Scheduler command <code>database:backup --keep=168</code>.</p>

            <form id="database-import-form" method="POST" action="{{ route('admin.server.database.import') }}" enctype="multipart/form-data" data-sm-confirm="This will overwrite current database data. Continue with import?" data-sm-confirm-button="Import Backup" class="mb-4">
                @csrf
                <div class="flex flex-col md:flex-row md:items-end gap-3">
                    <div class="flex-1">
                        <x-ui.file-upload name="database_backup" id="database_backup" label="Import Backup (.sql or .sql.gz)" accept=".sql,.gz,.sql.gz" />
                    </div>
                </div>
            </form>

            <h4 class="font-semibold mb-2">Available Backups</h4>
            @if($databaseBackups->isEmpty())
                <p class="text-sm text-gray-600">No backup files found yet.</p>
            @else
                <div class="overflow-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-3 py-2">File</th>
                            <th class="text-left px-3 py-2">Modified</th>
                            <th class="text-left px-3 py-2">Size</th>
                            <th class="text-left px-3 py-2">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($databaseBackups as $backup)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-mono text-xs break-all">{{ $backup['filename'] }}</td>
                                <td class="px-3 py-2">{{ $backup['modified_at'] }}</td>
                                <td class="px-3 py-2">{{ \App\Helpers::bytesToString((int) $backup['size']) }}</td>
                                <td class="px-3 py-2">
                                    <div class="flex items-center gap-3">
                                        <a href="{{ route('admin.server.database.download', ['filename' => $backup['filename']]) }}" class="hover:text-primary-color" title="Download backup">
                                            <i class="fa-solid fa-download"></i>
                                        </a>
                                        <form method="POST" action="{{ route('admin.server.database.delete', ['filename' => $backup['filename']]) }}" data-sm-confirm="Delete this backup file? This cannot be undone." data-sm-confirm-button="Delete Backup">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="hover:text-red-600" title="Delete backup">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="mt-3">
                    {{ $databaseBackups->links() }}
                </div>
            @endif
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">File Backups</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="font-semibold mb-1">Media Files</div>
                    <div class="text-xs text-gray-600 mb-3">
                        {{ number_format((int) ($mediaStats['count'] ?? 0)) }} files
                        •
                        {{ \App\Helpers::bytesToString((int) ($mediaStats['size'] ?? 0)) }}
                    </div>
                    <x-ui.button type="link" color="outline" href="{{ route('admin.server.media.download-all') }}">Download ZIP</x-ui.button>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3">
                    <div class="font-semibold mb-1">Finance Files</div>
                    <div class="text-xs text-gray-600 mb-3">
                        {{ number_format((int) ($financeStats['count'] ?? 0)) }} files
                        •
                        {{ \App\Helpers::bytesToString((int) ($financeStats['size'] ?? 0)) }}
                    </div>
                    <x-ui.button type="link" color="outline" href="{{ route('admin.server.finance.download-all') }}">Download ZIP</x-ui.button>
                </div>
            </div>
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Runtime</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-2 text-sm">
                @foreach($serverInfo as $label => $value)
                <div class="py-1 border-b border-gray-100">
                    <span class="font-semibold">{{ $label }}:</span>
                    <span class="break-all">{{ $value }}</span>
                </div>
                @endforeach
            </div>
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">Laravel Log</h3>
                <form method="POST" action="{{ route('admin.server.log.clear') }}" data-sm-confirm="Clear laravel.log? This cannot be undone." data-sm-confirm-button="Clear Log">
                    @csrf
                    <x-ui.button type="submit" color="danger">Clear Log</x-ui.button>
                </form>
            </div>

            <div class="text-xs text-gray-600 mb-3">
                <p><strong>Path:</strong> <code>{{ $logPath }}</code></p>
                <p><strong>Size:</strong> <span id="laravel-log-size">{{ number_format($logSize) }}</span> bytes</p>
                <p><strong>Last Modified:</strong> <span id="laravel-log-updated">{{ $logModifiedAt ?? 'N/A' }}</span></p>
                <p><strong>Showing:</strong> Last 300 lines</p>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <button type="button" id="laravel-log-refresh" class="whitespace-nowrap text-center justify-center rounded-md px-4 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition hover:bg-gray-500 focus-visible:outline-primary-color text-gray-800 border border-gray-400 bg-white hover:text-white">Refresh Log</button>
                </div>
            </div>

            @if(!$logExists)
            <p id="laravel-log-empty" class="text-sm text-gray-600">Log file not found.</p>
            <pre id="laravel-log-content" class="hidden text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[40rem] whitespace-pre-wrap"></pre>
            @elseif(trim($logContent) === '')
            <p id="laravel-log-empty" class="text-sm text-gray-600">Log file is empty.</p>
            <pre id="laravel-log-content" class="hidden text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[40rem] whitespace-pre-wrap"></pre>
            @else
            <p id="laravel-log-empty" class="hidden text-sm text-gray-600">Log file is empty.</p>
            <pre id="laravel-log-content" class="text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[40rem] whitespace-pre-wrap">{{ $logContent }}</pre>
            @endif
        </div>
    </x-container>
</x-layout>

<script>
    const initServerLogControls = () => {
        const updatedEl = document.getElementById('deploy-log-updated');
        const autoRefreshEl = document.getElementById('deploy-log-auto-refresh');
        const emptyEl = document.getElementById('deploy-log-empty');
        const contentEl = document.getElementById('deploy-log-content');
        const endpoint = "{{ route('admin.server.deploy.log') }}";
        const laravelUpdatedEl = document.getElementById('laravel-log-updated');
        const laravelSizeEl = document.getElementById('laravel-log-size');
        const laravelEmptyEl = document.getElementById('laravel-log-empty');
        const laravelContentEl = document.getElementById('laravel-log-content');
        const laravelEndpoint = "{{ route('admin.server.log') }}";
        const databaseImportInput = document.getElementById('database_backup');
        const databaseImportForm = document.getElementById('database-import-form');
        const setButtonLoading = (button, isLoading, label = 'Refreshing...') => {
            if (!(button instanceof HTMLButtonElement)) {
                return;
            }

            if (isLoading) {
                if (!button.dataset.originalHtml) {
                    button.dataset.originalHtml = button.innerHTML;
                }
                button.disabled = true;
                button.setAttribute('aria-busy', 'true');
                button.innerHTML = `<span class="altcha-processing-content"><span class="altcha-inline-spinner" aria-hidden="true"></span><span>${label}</span></span>`;
                return;
            }

            if (button.dataset.originalHtml) {
                button.innerHTML = button.dataset.originalHtml;
                delete button.dataset.originalHtml;
            }
            button.disabled = false;
            button.removeAttribute('aria-busy');
        };

        const scrollToBottom = (el) => {
            if (!el) {
                return;
            }
            el.scrollTop = el.scrollHeight;
        };

        const fetchJson = (url) => {
            const bustUrl = url + (url.includes('?') ? '&' : '?') + '_=' + Date.now();
            return fetch(bustUrl, {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            }).then((response) => {
                if (!response.ok) {
                    throw new Error('Request failed with status ' + response.status);
                }
                return response.json();
            });
        };

        const refreshLog = (respectAutoRefresh = true) => {
            if (!updatedEl || !emptyEl || !contentEl) {
                return Promise.resolve();
            }
            if (respectAutoRefresh && autoRefreshEl && !autoRefreshEl.checked) {
                return Promise.resolve();
            }

            return fetchJson(endpoint)
                .then((data) => {
                    updatedEl.textContent = data.modified_at || 'N/A';
                    const content = (data.content || '').trim();

                    if (!data.exists || content === '') {
                        emptyEl.classList.remove('hidden');
                        contentEl.classList.add('hidden');
                        emptyEl.textContent = 'Log file is empty.';
                        return;
                    }

                    emptyEl.classList.add('hidden');
                    contentEl.classList.remove('hidden');
                    contentEl.textContent = data.content;
                    scrollToBottom(contentEl);
                })
                .catch(() => {
                    // Keep the last known content if polling fails.
                });
        };

        const refreshLaravelLog = () => {
            if (!laravelUpdatedEl || !laravelSizeEl || !laravelEmptyEl || !laravelContentEl) {
                return Promise.resolve();
            }

            return fetchJson(laravelEndpoint)
                .then((data) => {
                    if (laravelUpdatedEl) {
                        laravelUpdatedEl.textContent = data.modified_at || 'N/A';
                    }
                    if (laravelSizeEl) {
                        const size = Number(data.size || 0);
                        laravelSizeEl.textContent = size.toLocaleString();
                    }

                    const content = (data.content || '').trim();
                    if (!laravelEmptyEl || !laravelContentEl) {
                        return;
                    }

                    if (!data.exists || content === '') {
                        laravelEmptyEl.classList.remove('hidden');
                        laravelContentEl.classList.add('hidden');
                        laravelEmptyEl.textContent = data.exists ? 'Log file is empty.' : 'Log file not found.';
                        return;
                    }

                    laravelEmptyEl.classList.add('hidden');
                    laravelContentEl.classList.remove('hidden');
                    laravelContentEl.textContent = data.content;
                    scrollToBottom(laravelContentEl);
                })
                .catch(() => {
                    // Keep the last known content if polling fails.
                });
        };

        document.addEventListener('click', (event) => {
            const target = event.target;
            if (!(target instanceof Element)) {
                return;
            }

            const deployRefreshButton = target.closest('#deploy-log-refresh');
            if (deployRefreshButton instanceof HTMLButtonElement) {
                event.preventDefault();
                setButtonLoading(deployRefreshButton, true, 'Refreshing...');
                refreshLog(false).finally(() => {
                    setButtonLoading(deployRefreshButton, false);
                });
            }

            const laravelRefreshButton = target.closest('#laravel-log-refresh');
            if (laravelRefreshButton instanceof HTMLButtonElement) {
                event.preventDefault();
                setButtonLoading(laravelRefreshButton, true, 'Refreshing...');
                refreshLaravelLog().finally(() => {
                    setButtonLoading(laravelRefreshButton, false);
                });
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) {
                return;
            }
            if (form.dataset.confirmedSubmit === '1') {
                delete form.dataset.confirmedSubmit;
                return;
            }

            const message = (form.dataset.smConfirm || '').trim();
            if (message === '') {
                return;
            }

            event.preventDefault();
            if (window.SM && typeof window.SM.confirm === 'function') {
                window.SM.confirm(
                    'Confirm action',
                    message,
                    (form.dataset.smConfirmButton || 'Confirm'),
                    (isConfirmed) => {
                        if (!isConfirmed) {
                            return;
                        }
                        form.dataset.confirmedSubmit = '1';
                        form.requestSubmit();
                    }
                );
            }
        });

        if (databaseImportInput instanceof HTMLInputElement && databaseImportForm instanceof HTMLFormElement) {
            databaseImportInput.addEventListener('change', () => {
                if (!databaseImportInput.files || databaseImportInput.files.length === 0) {
                    return;
                }

                databaseImportForm.requestSubmit();
            });
        }

        refreshLog(false);
        refreshLaravelLog();
        setInterval(() => refreshLog(true), 10000);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServerLogControls, {
            once: true
        });
    } else {
        initServerLogControls();
    }
</script>
