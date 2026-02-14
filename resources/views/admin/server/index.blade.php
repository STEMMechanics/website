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
            </div>
            <div class="text-xs text-gray-600 mb-3">
                <p><strong>Output Log:</strong> <code>{{ $deployOutputPath }}</code></p>
                <p><strong>Last Output Update:</strong> {{ $deployOutputModifiedAt ?? 'N/A' }}</p>
                <p><strong>Showing:</strong> Last 150 lines</p>
            </div>
            @if(!$deployOutputExists)
                <p class="text-sm text-gray-600">Deploy output log not found yet.</p>
            @elseif(trim($deployOutputContent) === '')
                <p class="text-sm text-gray-600">Deploy output log is empty.</p>
            @else
                <pre class="text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[20rem] whitespace-pre-wrap">{{ $deployOutputContent }}</pre>
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
                <p><strong>Size:</strong> {{ number_format($logSize) }} bytes</p>
                <p><strong>Last Modified:</strong> {{ $logModifiedAt ?? 'N/A' }}</p>
                <p><strong>Showing:</strong> Last 300 lines</p>
            </div>

            @if(!$logExists)
                <p class="text-sm text-gray-600">Log file not found.</p>
            @elseif(trim($logContent) === '')
                <p class="text-sm text-gray-600">Log file is empty.</p>
            @else
                <pre class="text-xs bg-gray-900 text-gray-100 rounded-md p-4 overflow-auto max-h-[40rem] whitespace-pre-wrap">{{ $logContent }}</pre>
            @endif
        </div>
    </x-container>
</x-layout>
