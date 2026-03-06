<x-layout>
    <x-mast title="STEMCraft" :tabs="[
        ['title' => 'Accounts', 'route' => route('admin.stemcraft.index')],
        ['title' => 'Punishments', 'route' => route('admin.stemcraft.punishments.index')],
        ['title' => 'Messaging', 'route' => route('admin.stemcraft.messages.index')],
        ['title' => 'Webhooks', 'route' => route('admin.stemcraft.webhooks.index')],
        ['title' => 'Management', 'route' => route('admin.stemcraft.management.index')],
    ]" />

    <x-container class="mt-8" inner-class="flex flex-col gap-8">
        <div id="stemcraft-management-status">
            @include('admin.stemcraft.partials.management-status', [
                'connection' => $connection,
                'statusError' => $statusError,
                'statusCards' => $statusCards,
                'worldRows' => $worldRows,
                'serverDetails' => $serverDetails,
            ])
        </div>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-gray-900">Run Command</h2>
            <p class="mt-2 text-sm leading-6 text-gray-600">Runs a console command through `server.command.request`. The plugin captures plain text output and may truncate it based on its config.</p>

            <form method="POST" action="{{ route('admin.stemcraft.management.execute') }}" class="mt-6" id="stemcraft-management-command-form">
                @csrf
                <x-ui.input
                    name="command"
                    label="Command"
                    value="{{ old('command', $lastCommand) }}"
                    info="Example: list, say Server restarting in 5 minutes, whitelist reload"
                />

                <div class="mt-5 flex justify-end">
                    <x-ui.button type="submit">Run command</x-ui.button>
                </div>
            </form>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <h2 class="text-xl font-semibold text-gray-900">Last Command Result</h2>

            <div class="mt-4" data-management-command-current>
                @if(trim($lastCommand) !== '')
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Command</div>
                        <div class="mt-1 font-mono text-sm text-gray-900 break-all">{{ is_array($lastCommandResult) ? ($lastCommandResult['command'] ?? $lastCommand) : $lastCommand }}</div>
                    </div>
                @endif

                @if($lastCommandError)
                    <div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">{{ $lastCommandError }}</div>
                @elseif(is_array($lastCommandResult))
                    <div class="mt-4 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Result</div>
                            <div class="mt-1 text-sm font-semibold {{ ($lastCommandResult['success'] ?? false) ? 'text-green-700' : 'text-red-700' }}">
                                {{ ($lastCommandResult['success'] ?? false) ? 'Success' : 'Failed' }}
                            </div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Duration</div>
                            <div class="mt-1 text-sm text-gray-900">{{ (int) ($lastCommandResult['duration_millis'] ?? 0) }} ms</div>
                        </div>
                        <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Output</div>
                            <div class="mt-1 text-sm text-gray-900">{{ ($lastCommandResult['truncated'] ?? false) ? 'Truncated' : 'Complete' }}</div>
                        </div>
                    </div>

                    @if(! empty($lastCommandResult['timestamp']))
                        <div class="mt-4 text-xs text-gray-500">Captured at {{ $lastCommandResult['timestamp'] }}</div>
                    @endif

                    <pre class="mt-4 max-h-[28rem] overflow-auto rounded-2xl bg-gray-50 p-4 text-xs text-gray-800 whitespace-pre-wrap">{{ (string) ($lastCommandResult['output'] ?? '(No output returned.)') }}</pre>
                @else
                    <p class="text-sm text-gray-500">No command has been run yet in this session.</p>
                @endif
            </div>
        </section>

        <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h2 class="text-xl font-semibold text-gray-900">Command History</h2>
                    <p class="mt-2 text-sm leading-6 text-gray-600">Keeps the most recent commands from this page while it stays open. A full page reload clears this history.</p>
                </div>
            </div>

            <div class="mt-4" data-management-command-history>
                <p class="text-sm text-gray-500">No commands have been run in this page session yet.</p>
            </div>
        </section>
    </x-container>

    @push('scripts')
        <script>
            document.addEventListener('DOMContentLoaded', () => {
                const results = document.getElementById('stemcraft-management-status');
                const snapshotUrl = @js(route('admin.stemcraft.management.snapshot'));
                const commandForm = document.getElementById('stemcraft-management-command-form');
                const commandCurrent = document.querySelector('[data-management-command-current]');
                const commandHistory = document.querySelector('[data-management-command-history]');

                if (!results || !snapshotUrl) {
                    return;
                }

                let refreshing = false;
                const commandEntries = [];

                const setRefreshing = (isRefreshing) => {
                    results.querySelectorAll('[data-management-refresh]').forEach((button) => {
                        if (!(button instanceof HTMLButtonElement)) {
                            return;
                        }

                        button.disabled = isRefreshing;
                        button.textContent = isRefreshing ? 'Refreshing...' : 'Refresh status';
                    });
                };

                const buildSnapshotUrl = () => {
                    const url = new URL(snapshotUrl, window.location.origin);
                    url.searchParams.set('_refresh', Date.now().toString());

                    return url.toString();
                };

                const refresh = async () => {
                    if (refreshing) {
                        return;
                    }

                    refreshing = true;
                    setRefreshing(true);

                    try {
                        const response = await fetch(buildSnapshotUrl(), {
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            cache: 'no-store',
                        });

                        if (!response.ok) {
                            return;
                        }

                        const payload = await response.json();
                        const replaced = SM.replaceHtmlPreservingState(results, payload?.resultsHtml || '');

                        if (replaced && window.Alpine?.initTree) {
                            window.Alpine.initTree(results);
                        }
                    } catch (_error) {
                    } finally {
                        refreshing = false;
                        setRefreshing(false);
                    }
                };

                results.addEventListener('click', (event) => {
                    const target = event.target instanceof Element ? event.target.closest('[data-management-refresh]') : null;
                    if (!target) {
                        return;
                    }

                    event.preventDefault();
                    refresh();
                });

                window.setInterval(() => {
                    if (document.visibilityState === 'visible') {
                        refresh();
                    }
                }, 10000);

                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        refresh();
                    }
                });

                if (!commandForm || !(commandCurrent instanceof HTMLElement) || !(commandHistory instanceof HTMLElement)) {
                    return;
                }

                const commandInput = commandForm.elements.namedItem('command');
                const csrfTokenInput = commandForm.elements.namedItem('_token');

                const escapeHtml = (value) => String(value ?? '')
                    .replaceAll('&', '&amp;')
                    .replaceAll('<', '&lt;')
                    .replaceAll('>', '&gt;')
                    .replaceAll('"', '&quot;')
                    .replaceAll("'", '&#039;');

                const historyTimestamp = (entry) => {
                    if (entry?.result?.timestamp) {
                        return String(entry.result.timestamp);
                    }

                    return String(entry?.recordedAt ?? '');
                };

                const renderCurrentEntry = (entry) => {
                    if (!entry) {
                        commandCurrent.innerHTML = '<p class="text-sm text-gray-500">No command has been run yet in this session.</p>';
                        return;
                    }

                    const commandLabel = escapeHtml(entry?.result?.command || entry?.command || '');
                    const hasError = typeof entry?.error === 'string' && entry.error !== '';
                    const isSuccess = !hasError && Boolean(entry?.result?.success);
                    const duration = Number.parseInt(String(entry?.result?.duration_millis ?? 0), 10) || 0;
                    const outputState = entry?.result?.truncated ? 'Truncated' : 'Complete';
                    const output = escapeHtml(entry?.result?.output || '(No output returned.)');
                    const capturedAt = escapeHtml(historyTimestamp(entry));

                    let html = '';

                    if (commandLabel !== '') {
                        html += `
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Command</div>
                                <div class="mt-1 break-all font-mono text-sm text-gray-900">${commandLabel}</div>
                            </div>
                        `;
                    }

                    if (hasError) {
                        html += `<div class="mt-4 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">${escapeHtml(entry.error)}</div>`;
                        commandCurrent.innerHTML = html;
                        return;
                    }

                    html += `
                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Result</div>
                                <div class="mt-1 text-sm font-semibold ${isSuccess ? 'text-green-700' : 'text-red-700'}">${isSuccess ? 'Success' : 'Failed'}</div>
                            </div>
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Duration</div>
                                <div class="mt-1 text-sm text-gray-900">${duration} ms</div>
                            </div>
                            <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4">
                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Output</div>
                                <div class="mt-1 text-sm text-gray-900">${escapeHtml(outputState)}</div>
                            </div>
                        </div>
                    `;

                    if (capturedAt !== '') {
                        html += `<div class="mt-4 text-xs text-gray-500">Captured at ${capturedAt}</div>`;
                    }

                    html += `<pre class="mt-4 max-h-[28rem] overflow-auto rounded-2xl bg-gray-50 p-4 text-xs text-gray-800 whitespace-pre-wrap">${output}</pre>`;

                    commandCurrent.innerHTML = html;
                };

                const renderHistory = () => {
                    if (commandEntries.length === 0) {
                        commandHistory.innerHTML = '<p class="text-sm text-gray-500">No commands have been run in this page session yet.</p>';
                        return;
                    }

                    commandHistory.innerHTML = commandEntries.map((entry, index) => {
                        const commandLabel = escapeHtml(entry?.result?.command || entry?.command || '');
                        const timestamp = escapeHtml(historyTimestamp(entry));
                        const hasError = typeof entry?.error === 'string' && entry.error !== '';
                        const isSuccess = !hasError && Boolean(entry?.result?.success);
                        const duration = Number.parseInt(String(entry?.result?.duration_millis ?? 0), 10) || 0;
                        const output = escapeHtml(entry?.result?.output || (hasError ? entry.error : '(No output returned.)'));
                        const outputState = entry?.result?.truncated ? 'Truncated' : 'Complete';

                        return `
                            <details class="rounded-2xl border border-gray-200 bg-gray-50 px-4 py-3 ${index === 0 ? 'open' : ''}">
                                <summary class="cursor-pointer text-sm font-semibold text-gray-900">
                                    ${commandLabel !== '' ? commandLabel : 'Command'}
                                    <span class="ml-2 text-xs font-normal ${hasError ? 'text-red-700' : (isSuccess ? 'text-green-700' : 'text-amber-700')}">${hasError ? 'Error' : (isSuccess ? 'Success' : 'Failed')}</span>
                                </summary>
                                <div class="mt-3 space-y-3 text-sm text-gray-700">
                                    ${timestamp !== '' ? `<div class="text-xs text-gray-500">Captured at ${timestamp}</div>` : ''}
                                    ${hasError ? `<div class="rounded-xl border border-red-200 bg-red-50 p-3 text-red-800">${escapeHtml(entry.error)}</div>` : `
                                        <div class="grid gap-3 md:grid-cols-3">
                                            <div class="rounded-xl border border-gray-200 bg-white p-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Result</div>
                                                <div class="mt-1 text-sm font-semibold ${isSuccess ? 'text-green-700' : 'text-red-700'}">${isSuccess ? 'Success' : 'Failed'}</div>
                                            </div>
                                            <div class="rounded-xl border border-gray-200 bg-white p-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Duration</div>
                                                <div class="mt-1 text-sm text-gray-900">${duration} ms</div>
                                            </div>
                                            <div class="rounded-xl border border-gray-200 bg-white p-3">
                                                <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Output</div>
                                                <div class="mt-1 text-sm text-gray-900">${escapeHtml(outputState)}</div>
                                            </div>
                                        </div>
                                    `}
                                    <pre class="max-h-56 overflow-auto rounded-xl bg-white p-3 text-xs text-gray-800 whitespace-pre-wrap">${output}</pre>
                                </div>
                            </details>
                        `;
                    }).join('');
                };

                const storeCommandEntry = (entry) => {
                    commandEntries.unshift(entry);
                    if (commandEntries.length > 12) {
                        commandEntries.length = 12;
                    }

                    renderCurrentEntry(entry);
                    renderHistory();
                };

                commandForm.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    if (!(commandInput instanceof HTMLInputElement || commandInput instanceof HTMLTextAreaElement)) {
                        return;
                    }

                    const commandValue = commandInput.value.trim();
                    if (commandValue === '') {
                        renderCurrentEntry({
                            command: '',
                            error: 'Command is required.',
                            recordedAt: new Date().toISOString(),
                        });
                        return;
                    }

                    const csrfToken = csrfTokenInput instanceof HTMLInputElement ? csrfTokenInput.value : '';
                    const formData = new FormData(commandForm);

                    SM.setFormProcessing(commandForm, true, { submitLabel: 'Running...' });

                    try {
                        const response = await fetch(commandForm.action, {
                            method: 'POST',
                            headers: {
                                Accept: 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            credentials: 'same-origin',
                            body: new URLSearchParams(formData),
                        });

                        const payload = await response.json().catch(() => null);

                        if (!response.ok) {
                            if (response.status === 422) {
                                const validationMessage = payload?.errors
                                    ? Object.values(payload.errors).flat()[0]
                                    : (payload?.message || 'Command could not be sent.');

                                renderCurrentEntry({
                                    command: commandValue,
                                    error: validationMessage || 'Command could not be sent.',
                                    recordedAt: new Date().toISOString(),
                                });

                                return;
                            }

                            const errorEntry = {
                                command: payload?.command || commandValue,
                                error: payload?.error || payload?.message || 'Command failed.',
                                recordedAt: new Date().toISOString(),
                            };

                            commandInput.value = '';
                            storeCommandEntry(errorEntry);

                            return;
                        }

                        const entry = {
                            command: payload?.command || commandValue,
                            result: payload?.result || null,
                            error: payload?.ok === false ? (payload?.error || 'Command failed.') : '',
                            recordedAt: new Date().toISOString(),
                        };

                        commandInput.value = '';
                        storeCommandEntry(entry);
                    } catch (_error) {
                        renderCurrentEntry({
                            command: commandValue,
                            error: 'Command failed before a response was received.',
                            recordedAt: new Date().toISOString(),
                        });
                    } finally {
                        SM.setFormProcessing(commandForm, false);
                    }
                });
            });
        </script>
    @endpush
</x-layout>
