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
                    <div><span class="inline-block w-52 font-semibold">Expense files scanned:</span> {{ number_format((int) ($summary['expense_files_scanned'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Expense files referenced:</span> {{ number_format((int) ($summary['expense_files_referenced'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Orphan expense files:</span> {{ number_format((int) ($summary['orphan_expense_files'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Missing expense references:</span> {{ number_format((int) ($summary['missing_expense_files'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Media files scanned:</span> {{ number_format((int) ($summary['media_files_scanned'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Media files referenced:</span> {{ number_format((int) ($summary['media_files_referenced'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Orphan media files:</span> {{ number_format((int) ($summary['orphan_media_files'] ?? 0)) }}</div>
                    <div><span class="inline-block w-52 font-semibold">Missing media references:</span> {{ number_format((int) ($summary['missing_media_files'] ?? 0)) }}</div>
                </div>
            </div>

            <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <div class="flex items-center justify-between gap-2 mb-3">
                    <h3 class="text-lg font-bold">Orphan Files</h3>
                    <span class="inline-flex items-center gap-3">
                        <a class="hover:text-primary-color" title="Download all orphan files" href="{{ route('admin.server.orphans.download-all', ['scope' => 'orphan_all']) }}"><i class="fa-solid fa-download"></i></a>
                        <form method="POST" class="inline" action="{{ route('admin.server.orphans.delete-all') }}" x-data x-on:submit.prevent="SM.confirm('Delete all orphans?', 'Delete all orphan expense and media files found in this scan?', 'Delete All', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                            @csrf
                            <input type="hidden" name="scope" value="orphan_all">
                            <button type="submit" class="hover:text-red-600" title="Delete all orphan files"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </span>
                </div>

                <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="font-semibold">Expense Files <span class="text-xs font-normal text-gray-500">(not referenced)</span></div>
                    <span class="inline-flex items-center gap-3">
                        <a class="hover:text-primary-color" title="Download all orphan expense files" href="{{ route('admin.server.orphans.download-all', ['scope' => 'orphan_expense']) }}"><i class="fa-solid fa-download"></i></a>
                        <form method="POST" class="inline" action="{{ route('admin.server.orphans.delete-all') }}" x-data x-on:submit.prevent="SM.confirm('Delete expense orphans?', 'Delete all orphan expense files from this scan?', 'Delete Expense', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                            @csrf
                            <input type="hidden" name="scope" value="orphan_expense">
                            <button type="submit" class="hover:text-red-600" title="Delete all orphan expense files"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </span>
                </div>
                @if($orphanExpenseFiles->isEmpty())
                    <p class="text-sm text-gray-600 mb-4">No orphan expense files found.</p>
                @else
                    <ul class="text-sm text-gray-800 list-disc list-inside mb-4 max-h-52 overflow-auto space-y-1">
                        @foreach($orphanExpenseFiles as $entry)
                            <li>
                                <code>{{ $entry['path'] }}</code>
                                <span class="ml-2 inline-flex items-center gap-3 align-middle">
                                    <a class="hover:text-primary-color" target="_blank" title="View file" href="{{ route('admin.server.orphans.file', ['disk' => 'local', 'path' => $entry['path']]) }}"><i class="fa-solid fa-eye"></i></a>
                                    <a class="hover:text-primary-color" title="Download file" href="{{ route('admin.server.orphans.file', ['disk' => 'local', 'path' => $entry['path'], 'download' => 1]) }}"><i class="fa-solid fa-download"></i></a>
                                    <form method="POST" class="inline" action="{{ route('admin.server.orphans.delete-file') }}" x-data x-on:submit.prevent="SM.confirm('Delete orphan file?', 'Delete this orphan file permanently?', 'Delete', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                                        @csrf
                                        <input type="hidden" name="disk" value="local">
                                        <input type="hidden" name="path" value="{{ $entry['path'] }}">
                                        <button type="submit" class="hover:text-red-600" title="Delete file"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif

                <div class="flex items-center justify-between gap-2 mb-2">
                    <div class="font-semibold">Media Files <span class="text-xs font-normal text-gray-500">(not referenced)</span></div>
                    <span class="inline-flex items-center gap-3">
                        <a class="hover:text-primary-color" title="Download all orphan media files" href="{{ route('admin.server.orphans.download-all', ['scope' => 'orphan_media']) }}"><i class="fa-solid fa-download"></i></a>
                        <form method="POST" class="inline" action="{{ route('admin.server.orphans.delete-all') }}" x-data x-on:submit.prevent="SM.confirm('Delete media orphans?', 'Delete all orphan media files from this scan?', 'Delete Media', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                            @csrf
                            <input type="hidden" name="scope" value="orphan_media">
                            <button type="submit" class="hover:text-red-600" title="Delete all orphan media files"><i class="fa-solid fa-trash"></i></button>
                        </form>
                    </span>
                </div>
                @if($orphanMediaFiles->isEmpty())
                    <p class="text-sm text-gray-600">No orphan media files found.</p>
                @else
                    <ul class="text-sm text-gray-800 list-disc list-inside max-h-52 overflow-auto space-y-1">
                        @foreach($orphanMediaFiles as $entry)
                            <li>
                                <code>{{ $entry['path'] }}</code>
                                <span class="ml-2 inline-flex items-center gap-3 align-middle">
                                    <a class="hover:text-primary-color" target="_blank" title="View file" href="{{ route('admin.server.orphans.file', ['disk' => 'media', 'path' => $entry['path']]) }}"><i class="fa-solid fa-eye"></i></a>
                                    <a class="hover:text-primary-color" title="Download file" href="{{ route('admin.server.orphans.file', ['disk' => 'media', 'path' => $entry['path'], 'download' => 1]) }}"><i class="fa-solid fa-download"></i></a>
                                    <form method="POST" class="inline" action="{{ route('admin.server.orphans.delete-file') }}" x-data x-on:submit.prevent="SM.confirm('Delete orphan file?', 'Delete this orphan file permanently?', 'Delete', (isConfirmed) => { if (isConfirmed) { $el.submit(); } })">
                                        @csrf
                                        <input type="hidden" name="disk" value="media">
                                        <input type="hidden" name="path" value="{{ $entry['path'] }}">
                                        <button type="submit" class="hover:text-red-600" title="Delete file"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
                <h3 class="text-lg font-bold mb-3">Missing Referenced Files</h3>

                <div class="font-semibold mb-2">Expense Files <span class="text-xs font-normal text-gray-500">(referenced but missing)</span></div>
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

                <div class="font-semibold mb-2">Media Files <span class="text-xs font-normal text-gray-500">(referenced but missing)</span></div>
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
