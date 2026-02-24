<x-layout>
    <x-mast>Orphaned Files</x-mast>

    <x-container>
        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">On-Demand Orphan Scan</h3>
                <form method="POST" action="{{ route('admin.server.orphans.scan') }}" x-data="{ confirmedSubmit: false }" x-on:submit.prevent="
                    if (confirmedSubmit) {
                        confirmedSubmit = false;
                        $el.submit();
                        return;
                    }
                    if (window.SM && typeof window.SM.confirm === 'function') {
                        window.SM.confirm('Confirm action', 'Run orphaned file scan now?', 'Run Scan', (isConfirmed) => {
                            if (!isConfirmed) return;
                            confirmedSubmit = true;
                            $el.requestSubmit();
                        });
                    }
                ">
                    @csrf
                    <x-ui.button type="submit" color="dark">Run Scan</x-ui.button>
                </form>
            </div>
            <p class="text-sm text-gray-600">Scans storage for files not referenced by database records, and for DB references where files are missing.</p>
            @if($report)
                <p class="text-xs text-gray-500 mt-2">Last scan: {{ $report['scanned_at'] ?? '-' }}</p>
            @else
                <p class="text-xs text-gray-500 mt-2">No scan has been run yet.</p>
            @endif
        </div>

        @if($report)
            @php
                $summary = $report['summary'] ?? [];
                $orphans = $report['orphans'] ?? [];
                $missing = $report['missing_references'] ?? [];
                $orphanExpenseFiles = collect($orphans['expense_files'] ?? [])->map(function ($entry) {
                    return ['path' => is_array($entry) ? (string) ($entry['path'] ?? '') : (string) $entry];
                })->filter(fn ($entry) => $entry['path'] !== '')->values();
                $orphanMediaFiles = collect($orphans['media_files'] ?? [])->map(function ($entry) {
                    return ['path' => is_array($entry) ? (string) ($entry['path'] ?? '') : (string) $entry];
                })->filter(fn ($entry) => $entry['path'] !== '')->values();
                $missingExpenseFiles = collect($missing['expense_files'] ?? [])->map(function ($entry) {
                    if (is_array($entry)) {
                        return [
                            'path' => (string) ($entry['path'] ?? ''),
                            'expense_ids' => collect($entry['expense_ids'] ?? [])->map(fn ($id) => (int) $id)->filter()->values()->all(),
                        ];
                    }

                    return [
                        'path' => (string) $entry,
                        'expense_ids' => [],
                    ];
                })->filter(fn ($entry) => $entry['path'] !== '')->values();
                $missingMediaFiles = collect($missing['media_files'] ?? [])->map(function ($entry) {
                    if (is_array($entry)) {
                        return [
                            'path' => (string) ($entry['path'] ?? ''),
                            'references' => collect($entry['references'] ?? [])->filter(fn ($ref) => is_array($ref) && !empty($ref['media_name']))->values()->all(),
                        ];
                    }

                    return [
                        'path' => (string) $entry,
                        'references' => [],
                    ];
                })->filter(fn ($entry) => $entry['path'] !== '')->values();
            @endphp

            <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <h3 class="text-lg font-bold mb-3">Summary</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-sm">
                    <div><strong>Expense files scanned:</strong> {{ number_format((int) ($summary['expense_files_scanned'] ?? 0)) }}</div>
                    <div><strong>Expense files referenced:</strong> {{ number_format((int) ($summary['expense_files_referenced'] ?? 0)) }}</div>
                    <div><strong>Orphan expense files:</strong> {{ number_format((int) ($summary['orphan_expense_files'] ?? 0)) }}</div>
                    <div><strong>Missing expense references:</strong> {{ number_format((int) ($summary['missing_expense_files'] ?? 0)) }}</div>
                    <div><strong>Media files scanned:</strong> {{ number_format((int) ($summary['media_files_scanned'] ?? 0)) }}</div>
                    <div><strong>Media files referenced:</strong> {{ number_format((int) ($summary['media_files_referenced'] ?? 0)) }}</div>
                    <div><strong>Orphan media files:</strong> {{ number_format((int) ($summary['orphan_media_files'] ?? 0)) }}</div>
                    <div><strong>Missing media references:</strong> {{ number_format((int) ($summary['missing_media_files'] ?? 0)) }}</div>
                </div>
            </div>

            <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <h3 class="text-lg font-bold mb-3">Orphan Files</h3>
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-ui.button type="link" color="outline" href="{{ route('admin.server.orphans.download-all', ['scope' => 'orphan_all']) }}">Download All Orphans</x-ui.button>
                    <x-ui.button type="link" color="outline" href="{{ route('admin.server.orphans.download-all', ['scope' => 'orphan_expense']) }}">Download Expense Orphans</x-ui.button>
                    <x-ui.button type="link" color="outline" href="{{ route('admin.server.orphans.download-all', ['scope' => 'orphan_media']) }}">Download Media Orphans</x-ui.button>
                </div>

                <h4 class="font-semibold mb-2">Expense Files (not referenced)</h4>
                @if($orphanExpenseFiles->isEmpty())
                    <p class="text-sm text-gray-600 mb-4">No orphan expense files found.</p>
                @else
                    <ul class="text-sm text-gray-800 list-disc list-inside mb-4 max-h-52 overflow-auto space-y-1">
                        @foreach($orphanExpenseFiles as $entry)
                            <li>
                                <code>{{ $entry['path'] }}</code>
                                <a class="ml-2 text-primary-color hover:underline" target="_blank" href="{{ route('admin.server.orphans.file', ['disk' => 'local', 'path' => $entry['path']]) }}">View</a>
                                <a class="ml-2 text-primary-color hover:underline" href="{{ route('admin.server.orphans.file', ['disk' => 'local', 'path' => $entry['path'], 'download' => 1]) }}">Download</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h4 class="font-semibold mb-2">Media Files (not referenced)</h4>
                @if($orphanMediaFiles->isEmpty())
                    <p class="text-sm text-gray-600">No orphan media files found.</p>
                @else
                    <ul class="text-sm text-gray-800 list-disc list-inside max-h-52 overflow-auto space-y-1">
                        @foreach($orphanMediaFiles as $entry)
                            <li>
                                <code>{{ $entry['path'] }}</code>
                                <a class="ml-2 text-primary-color hover:underline" target="_blank" href="{{ route('admin.server.orphans.file', ['disk' => 'media', 'path' => $entry['path']]) }}">View</a>
                                <a class="ml-2 text-primary-color hover:underline" href="{{ route('admin.server.orphans.file', ['disk' => 'media', 'path' => $entry['path'], 'download' => 1]) }}">Download</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <h3 class="text-lg font-bold mb-3">Missing Referenced Files</h3>

                <h4 class="font-semibold mb-2">Expense Files (referenced but missing)</h4>
                @if($missingExpenseFiles->isEmpty())
                    <p class="text-sm text-gray-600 mb-4">No missing expense files found.</p>
                @else
                    <ul class="text-sm text-gray-800 list-disc list-inside mb-4 max-h-52 overflow-auto space-y-1">
                        @foreach($missingExpenseFiles as $entry)
                            <li>
                                <code>{{ $entry['path'] }}</code>
                                @if(!empty($entry['expense_ids']))
                                    @foreach($entry['expense_ids'] as $expenseId)
                                        <a class="ml-2 text-primary-color hover:underline" href="{{ route('admin.expense.edit', ['expense' => $expenseId]) }}">Expense #{{ $expenseId }}</a>
                                    @endforeach
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif

                <h4 class="font-semibold mb-2">Media Files (referenced but missing)</h4>
                @if($missingMediaFiles->isEmpty())
                    <p class="text-sm text-gray-600">No missing media files found.</p>
                @else
                    <ul class="text-sm text-gray-800 list-disc list-inside max-h-52 overflow-auto space-y-1">
                        @foreach($missingMediaFiles as $entry)
                            <li>
                                <code>{{ $entry['path'] }}</code>
                                @foreach($entry['references'] as $reference)
                                    @php
                                        $mediaName = (string) ($reference['media_name'] ?? '');
                                        $source = (string) ($reference['source'] ?? 'media');
                                    @endphp
                                    @if($mediaName !== '')
                                        <a class="ml-2 text-primary-color hover:underline" href="{{ route('admin.media.edit', ['media' => $mediaName]) }}">
                                            Media {{ $mediaName }} ({{ $source }})
                                        </a>
                                    @endif
                                @endforeach
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </x-container>
</x-layout>
