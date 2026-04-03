<x-layout>
    <x-mast>Server Info</x-mast>

    <x-container>
        <div id="server-info-app" data-csrf="{{ csrf_token() }}" data-maintenance-refresh-url="{{ route('admin.site_option.maintenance-refresh') }}">
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
                <h3 class="text-lg font-bold">Deployment</h3>
                <form method="POST" action="{{ route('admin.server.deploy') }}" data-sm-confirm="Run website updater with selected options? You may need to refresh the page afterward the update completes." data-sm-confirm-button="Run Update" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <x-ui.checkbox
                        name="current"
                        value="1"
                        label="Dev"
                        :noWrapper="true"
                        :inline="true" />
                    <x-ui.checkbox
                        name="force"
                        value="1"
                        label="Force"
                        :noWrapper="true"
                        :inline="true" />
                    <x-ui.button type="submit" color="dark">Run Update</x-ui.button>
                </form>
            </div>
            <div class="mb-4 flex flex-wrap items-center gap-3">
                <x-ui.button type="button" color="danger" id="server-info-maintenance-refresh-button">Clear Cache & Restart Queue</x-ui.button>
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
                        :inline="true" />
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
        </div>
    </x-container>
</x-layout>

<script>
    const initServerLogControls = () => {
        const serverInfoRoot = document.getElementById('server-info-app');
        const serverMaintenanceRefreshUrl = serverInfoRoot?.dataset.maintenanceRefreshUrl || '';
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

        const fetchJson = (url, options = {}) => {
            const bustUrl = url + (url.includes('?') ? '&' : '?') + '_=' + Date.now();
            return fetch(bustUrl, {
                ...options,
                cache: 'no-store',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': serverInfoRoot?.dataset.csrf || '',
                    ...(options.headers || {}),
                },
            }).then(async (response) => {
                const payload = await response.json().catch(() => ({}));
                if (!response.ok || payload.success === false) {
                    const error = new Error(payload.message || 'Request failed with status ' + response.status);
                    error.payload = payload;
                    error.status = response.status;
                    throw error;
                }
                return payload;
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

        const escapeHtml = (value) => String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');

        const maintenanceResultHtml = (payload) => {
            const commands = Array.isArray(payload?.commands) ? payload.commands : [];
            if (commands.length === 0) {
                return escapeHtml(String(payload?.message || 'No command results were returned.'));
            }

            const rows = commands.map((entry) => {
                const status = entry?.success ? 'OK' : 'Failed';
                const details = [entry?.output, entry?.error].filter((value) => String(value || '').trim() !== '').join(' ');
                return '<li><strong>' + escapeHtml(String(entry?.command || 'command')) + '</strong> - ' + escapeHtml(status) + (details !== '' ? ': ' + escapeHtml(details) : '') + '</li>';
            }).join('');

            return '<div class="space-y-2"><p>' + escapeHtml(String(payload?.message || 'Maintenance commands finished.')) + '</p><ul class="list-disc space-y-1 pl-5 text-left">' + rows + '</ul></div>';
        };

        const runMaintenance = async (button) => {
            if (!serverMaintenanceRefreshUrl) {
                return;
            }

            const execute = async () => {
                try {
                    button.disabled = true;
                    const payload = await fetchJson(serverMaintenanceRefreshUrl, {
                        method: 'POST',
                    });
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice('Maintenance complete', maintenanceResultHtml(payload), 'success', { toast: true });
                    }
                } catch (error) {
                    const payload = error?.payload || null;
                    const html = maintenanceResultHtml(payload || {
                        message: error.message || 'Could not run the maintenance commands.',
                        commands: [],
                    });
                    if (window.SM && typeof window.SM.notice === 'function') {
                        window.SM.notice('Maintenance failed', html, 'danger', { toast: true });
                    }
                } finally {
                    button.disabled = false;
                }
            };

            if (window.SM && typeof window.SM.confirm === 'function') {
                window.SM.confirm(
                    'Clear caches and restart queues?',
                    'This will run optimize:clear, config:clear, cache:clear, view:clear, and queue:restart on the server.',
                    'Run Maintenance',
                    (isConfirmed) => {
                        if (!isConfirmed) {
                            return;
                        }
                        void execute();
                    }
                );
                return;
            }

            void execute();
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

            const maintenanceRefreshButton = target.closest('#server-info-maintenance-refresh-button');
            if (maintenanceRefreshButton instanceof HTMLButtonElement) {
                event.preventDefault();
                void runMaintenance(maintenanceRefreshButton);
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
