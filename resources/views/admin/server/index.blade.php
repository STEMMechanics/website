<x-layout>
    <x-mast>Server Info</x-mast>

    <x-container>
        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">Deployment</h3>
                <form method="POST" action="{{ route('admin.server.deploy') }}" onsubmit="return confirm('Run deploy.sh with selected options now? This can restart queues and run migrations/build steps.');" class="flex flex-wrap items-center gap-3">
                    @csrf
                    <label class="inline-flex items-center text-sm gap-2">
                        <input type="checkbox" name="current" value="1" class="rounded border-gray-300">
                        <span>Current</span>
                    </label>
                    <label class="inline-flex items-center text-sm gap-2">
                        <input type="checkbox" name="force" value="1" checked class="rounded border-gray-300">
                        <span>Force</span>
                    </label>
                    <x-ui.button type="submit" color="dark">Run Update</x-ui.button>
                </form>
                <form method="POST" action="{{ route('admin.server.deploy.log.clear') }}" onsubmit="return confirm('Clear deploy output log? This cannot be undone.');">
                    @csrf
                    <x-ui.button type="submit" color="danger">Clear Deploy Log</x-ui.button>
                </form>
            </div>
            <div class="text-xs text-gray-600 mb-3">
                <p><strong>Output Log:</strong> <code>{{ $deployOutputPath }}</code></p>
                <p><strong>Last Output Update:</strong> <span id="deploy-log-updated">{{ $deployOutputModifiedAt ?? 'N/A' }}</span></p>
                <p><strong>Showing:</strong> Last 150 lines</p>
                <div class="flex flex-wrap items-center gap-3 mt-2">
                    <button type="button" id="deploy-log-refresh" class="whitespace-nowrap text-center justify-center rounded-md px-4 py-1.5 text-sm font-semibold leading-6 shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 transition hover:bg-gray-500 focus-visible:outline-primary-color text-gray-800 border border-gray-400 bg-white hover:text-white">Refresh Log</button>
                    <label class="inline-flex items-center text-sm gap-2">
                        <input type="checkbox" id="deploy-log-auto-refresh" checked class="rounded border-gray-300">
                        <span>Auto-refresh every 10 seconds</span>
                    </label>
                </div>
            </div>
            @if(!$deployOutputExists)
                <p id="deploy-log-empty" class="text-sm text-gray-600">Deploy output log not found yet.</p>
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
                <form method="POST" action="{{ route('admin.server.log.clear') }}" onsubmit="return confirm('Clear laravel.log? This cannot be undone.');">
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

@push('scripts')
<script>
    (() => {
        const updatedEl = document.getElementById('deploy-log-updated');
        const autoRefreshEl = document.getElementById('deploy-log-auto-refresh');
        const deployRefreshButtonEl = document.getElementById('deploy-log-refresh');
        const emptyEl = document.getElementById('deploy-log-empty');
        const contentEl = document.getElementById('deploy-log-content');
        const endpoint = "{{ route('admin.server.deploy.log') }}";
        const laravelUpdatedEl = document.getElementById('laravel-log-updated');
        const laravelSizeEl = document.getElementById('laravel-log-size');
        const laravelRefreshButtonEl = document.getElementById('laravel-log-refresh');
        const laravelEmptyEl = document.getElementById('laravel-log-empty');
        const laravelContentEl = document.getElementById('laravel-log-content');
        const laravelEndpoint = "{{ route('admin.server.log') }}";

        const refreshLog = (respectAutoRefresh = true) => {
            if (!updatedEl || !emptyEl || !contentEl) {
                return;
            }
            if (respectAutoRefresh && autoRefreshEl && !autoRefreshEl.checked) {
                return;
            }

            fetch(endpoint, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then((response) => response.json())
                .then((data) => {
                    updatedEl.textContent = data.modified_at || 'N/A';
                    const content = (data.content || '').trim();

                    if (!data.exists || content === '') {
                        emptyEl.classList.remove('hidden');
                        contentEl.classList.add('hidden');
                        emptyEl.textContent = data.exists ? 'Deploy output log is empty.' : 'Deploy output log not found yet.';
                        return;
                    }

                    emptyEl.classList.add('hidden');
                    contentEl.classList.remove('hidden');
                    contentEl.textContent = data.content;
                })
                .catch(() => {
                    // Keep the last known content if polling fails.
                });
        };

        const refreshLaravelLog = () => {
            if (!laravelUpdatedEl || !laravelSizeEl || !laravelEmptyEl || !laravelContentEl) {
                return;
            }

            fetch(laravelEndpoint, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            })
                .then((response) => response.json())
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
                })
                .catch(() => {
                    // Keep the last known content if polling fails.
                });
        };

        if (deployRefreshButtonEl) {
            deployRefreshButtonEl.addEventListener('click', () => refreshLog(false));
        }
        if (laravelRefreshButtonEl) {
            laravelRefreshButtonEl.addEventListener('click', refreshLaravelLog);
        }

        setInterval(() => refreshLog(true), 10000);
    })();
</script>
@endpush
