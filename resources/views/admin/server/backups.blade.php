<x-layout>
    <x-mast>Server Backups</x-mast>

    <x-container>
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

            <p class="text-xs text-gray-600 mb-3">Hourly backups are scheduled via Laravel Scheduler command <code>database:backup</code>. When <code>--keep</code> is omitted, retention uses site option <code>backup.database.keep</code> (currently {{ number_format((int) $databaseBackupKeepCount) }} files). Offsite backups can be run with <code>backup:remote</code> using the <code>backup.remote.*</code> site options.</p>

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
                                    <div class="flex flex-wrap items-center gap-3">
                                        <form method="POST" action="{{ route('admin.server.database.restore', ['filename' => $backup['filename']]) }}" data-sm-confirm="Rollback the live database to this backup? This will overwrite current data. Make sure you have a current backup first." data-sm-confirm-button="Rollback Database">
                                            @csrf
                                            <button type="submit" class="hover:text-orange-600" title="Rollback database to this backup" aria-label="Rollback database to this backup">
                                                <i class="fa-solid fa-rotate-left"></i>
                                            </button>
                                        </form>
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
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">File Backups</h3>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.server.files.backup-now') }}" data-sm-confirm="Create a full local file snapshot now?" data-sm-confirm-button="Backup Now">
                        @csrf
                        <x-ui.button type="submit" color="dark">Full Backup Now</x-ui.button>
                    </form>
                </div>
            </div>

            @if(is_array(session('file_backup_notice')))
                @php($fileNotice = session('file_backup_notice'))
                <div class="mb-3 rounded-md border px-3 py-2 text-sm {{ ($fileNotice['type'] ?? '') === 'success' ? 'border-green-200 bg-green-50 text-green-800' : 'border-red-200 bg-red-50 text-red-800' }}">
                    {{ (string) ($fileNotice['text'] ?? '') }}
                </div>
            @endif

            <p class="text-xs text-gray-600 mb-3">Monthly full backups are scheduled via <code>files:backup --full</code> and nightly incrementals via <code>files:backup --incremental --window=24h</code>. When <code>--keep</code> is omitted, retention uses <code>backup.files.full.keep</code> (currently {{ number_format((int) $fileBackupFullKeepCount) }} runs) and <code>backup.files.incremental.keep</code> (currently {{ number_format((int) $fileBackupIncrementalKeepCount) }} runs). File backups are written to <code>/storage/backups/files</code> for offsite sync and restore.</p>

            @if($fileBackups->isEmpty())
                <p class="text-sm text-gray-600">No file backups found yet.</p>
            @else
                <div class="overflow-auto border border-gray-200 rounded-lg">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <th class="text-left px-3 py-2">Run</th>
                            <th class="text-center px-3 py-2">Mode</th>
                            <th class="text-left px-3 py-2">Created</th>
                            <th class="text-center px-3 py-2">Files</th>
                            <th class="text-center px-3 py-2">Deleted</th>
                            <th class="text-left px-3 py-2">Size</th>
                            <th class="text-left px-3 py-2">Action</th>
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($fileBackups as $backup)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-mono text-xs break-all">
                                    <a class="text-primary-color hover:underline" href="{{ route('admin.server.files.show', ['mode' => $backup['mode'], 'filename' => $backup['filename']]) }}">
                                        {{ $backup['filename'] }}
                                    </a>
                                </td>
                                <td class="px-3 py-2 text-center">
                                    <span class="inline-flex items-center rounded-full border px-2.5 py-1 text-xs font-semibold whitespace-nowrap {{ (string) $backup['mode'] === \App\Services\FileBackupService::MODE_INCREMENTAL ? 'border-amber-200 bg-amber-50 text-amber-800' : 'border-sky-200 bg-sky-50 text-sky-800' }}">
                                        {{ ucfirst((string) $backup['mode']) }}
                                    </span>
                                    @if(! empty($backup['window_hours']))
                                        <div class="mt-1 text-xs text-gray-500">{{ number_format((int) $backup['window_hours']) }}h window</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2">
                                    {{ trim((string) ($backup['created_at'] ?? $backup['modified_at'] ?? '')) !== '' ? \Carbon\Carbon::parse((string) ($backup['created_at'] ?? $backup['modified_at']))->format('Y-m-d H:i:s') : '-' }}
                                </td>
                                <td class="px-3 py-2 text-center">{{ number_format((int) $backup['uploaded_files']) }}</td>
                                <td class="px-3 py-2 text-center">{{ number_format((int) $backup['deleted_files']) }}</td>
                                <td class="px-3 py-2">{{ \App\Helpers::bytesToString((int) $backup['size']) }}</td>
                                <td class="px-3 py-2">
                                    <a href="{{ route('admin.server.files.show', ['mode' => $backup['mode'], 'filename' => $backup['filename']]) }}" class="hover:text-primary-color" title="View files">
                                        <i class="fa-solid fa-folder-open"></i>
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Bulk File Download</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-3 flex justify-between">
                    <div>
                        <div class="font-semibold mb-1">Media Files</div>
                        <div class="text-xs text-gray-600 mb-3">
                            {{ number_format((int) ($mediaStats['count'] ?? 0)) }} files
                            •
                            {{ \App\Helpers::bytesToString((int) ($mediaStats['size'] ?? 0)) }}
                        </div>
                    </div>
                    <a
                        href="{{ route('admin.server.media.download-all') }}"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                        title="Download media ZIP"
                        aria-label="Download media ZIP"
                    >
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-3 flex justify-between">
                    <div>
                        <div class="font-semibold mb-1">Finance Files</div>
                        <div class="text-xs text-gray-600 mb-3">
                            {{ number_format((int) ($financeStats['count'] ?? 0)) }} files
                            •
                            {{ \App\Helpers::bytesToString((int) ($financeStats['size'] ?? 0)) }}
                        </div>
                    </div>
                    <a
                        href="{{ route('admin.server.finance.download-all') }}"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary-color"
                        title="Download finance ZIP"
                        aria-label="Download finance ZIP"
                    >
                        <i class="fa-solid fa-download"></i>
                    </a>
                </div>
            </div>
        </div>
    </x-container>
</x-layout>

<script>
    const initServerBackupControls = () => {
        const databaseImportInput = document.getElementById('database_backup');
        const databaseImportForm = document.getElementById('database-import-form');

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
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initServerBackupControls, {
            once: true
        });
    } else {
        initServerBackupControls();
    }
</script>
