<x-layout>
    <x-mast>Server Backups</x-mast>

    <x-container>
        <div
            x-data="serverBackupRunner({
                initialRuns: @js($activeBackupRuns),
                statusUrl: @js(route('admin.server.backups.status', ['backupRun' => '__RUN_ID__'])),
            })"
            x-init="init()"
        >
        <template x-for="run in runs" :key="run.id">
            <div class="my-4 rounded-lg border p-4 shadow-sm" :class="run.status === 'failed' ? 'border-red-200 bg-red-50' : (run.status === 'completed' ? 'border-green-200 bg-green-50' : 'border-sky-200 bg-sky-50')" role="status">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <div class="font-semibold" :class="run.status === 'failed' ? 'text-red-900' : (run.status === 'completed' ? 'text-green-900' : 'text-sky-950')" x-text="operationLabel(run.type)"></div>
                        <div class="mt-1 text-sm" :class="run.status === 'failed' ? 'text-red-800' : (run.status === 'completed' ? 'text-green-800' : 'text-sky-800')" x-text="run.status === 'failed' ? run.error_message : run.message"></div>
                    </div>
                    <span class="text-xs font-semibold uppercase tracking-wide" x-text="run.status"></span>
                </div>
                <div x-show="!run.finished" class="mt-3 h-2.5 overflow-hidden rounded-full bg-white/80">
                    <div class="h-full rounded-full bg-sky-600 transition-all duration-700" :class="run.status === 'running' ? 'animate-pulse' : ''" :style="`width: ${Math.max(5, Number(run.progress || 0))}%`"></div>
                </div>
            </div>
        </template>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">Database Backup</h3>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.server.database.backup-now') }}" @submit.prevent="startBackup($event, 'database')">
                        @csrf
                        <x-ui.button type="submit" color="dark" x-bind:disabled="isRunning('database')">Backup Now</x-ui.button>
                    </form>
{{--                    <form method="POST" action="{{ route('admin.server.database.export') }}" data-sm-confirm="Create and download a full database backup now?" data-sm-confirm-button="Export Backup">--}}
{{--                        @csrf--}}
{{--                        <x-ui.button type="submit" color="outline">Export (.sql.gz)</x-ui.button>--}}
{{--                    </form>--}}
                </div>
            </div>


            <p class="text-xs text-gray-600 mb-3">Hourly backups are scheduled via Laravel Scheduler command <code>database:backup</code>. When <code>--keep</code> is omitted, retention uses site option <code>backup.database.keep</code> (currently {{ number_format((int) $databaseBackupKeepCount) }} files). Offsite backups can be run with <code>backup:remote</code> using the <code>backup.remote.*</code> site options. Database and file backups run through the queue; local development requires <code>php artisan queue:work</code> in another terminal.</p>

            <form id="database-import-form" method="POST" action="{{ route('admin.server.database.import') }}" enctype="multipart/form-data" data-sm-confirm="This will overwrite current database data. Continue with import?" data-sm-confirm-button="Import Backup" class="mb-4">
                @csrf
                <div class="flex flex-col md:flex-row md:items-end gap-3">
                    <div class="flex-1">
                        <x-ui.file-upload name="database_backup" id="database_backup" label="Import Backup (.sql or .sql.gz)" accept=".sql,.gz,.sql.gz" />
                    </div>
                </div>
            </form>

            <div id="database-import-progress" class="hidden mb-4 rounded-lg border border-sky-200 bg-sky-50 p-4" role="status" aria-live="polite">
                <div class="flex items-center justify-between gap-3">
                    <p id="database-import-progress-message" class="text-sm font-semibold text-sky-950">Uploading backup…</p>
                    <span id="database-import-progress-percent" class="shrink-0 text-xs font-semibold text-sky-800">0%</span>
                </div>
                <div id="database-import-progress-track" class="mt-3 h-2.5 overflow-hidden rounded-full bg-white" role="progressbar" aria-label="Database backup upload" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                    <div id="database-import-progress-bar" class="h-full rounded-full bg-sky-600 transition-[width] duration-200" style="width: 0%"></div>
                </div>
                <p id="database-import-progress-detail" class="mt-2 text-xs text-sky-800">Keep this page open while the backup is uploaded.</p>
                <button id="database-import-reload" type="button" class="hidden mt-3 text-sm font-semibold text-primary-color hover:underline">Reload page</button>
            </div>

            <h4 class="font-semibold mb-2">Available Backups</h4>
            <x-ui.dynamic-list name="admin-server-backups">
            <x-ui.collection-controls class="my-4" />
            @if($databaseBackups->isEmpty())
                <p class="text-sm text-gray-600">No backup files found yet.</p>
            @else
                <div class="overflow-auto border border-gray-200 rounded-lg">
                    <x-ui.table variant="plain" table-class="sm-backup-table w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <x-ui.list-heading class="text-left px-3 py-2" label="File" />
                            <x-ui.list-heading class="px-3 py-2 text-center!" label="Modified" />
                            <x-ui.list-heading class="px-3 py-2 text-center!" label="Size" />
                            <x-ui.list-heading class="text-center! px-3 py-2" label="Actions" />
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($databaseBackups as $backup)
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-mono text-xs break-all" data-label="File">{{ $backup['filename'] }}</td>
                                <td class="px-3 py-2 text-center!" data-label="Modified"><x-ui.date-time>{{ $backup['modified_at'] }}</x-ui.date-time></td>
                                <td class="px-3 py-2 text-center!" data-label="Size"><x-ui.nonbreaking>{{ \App\Helpers::bytesToString((int) $backup['size']) }}</x-ui.nonbreaking></td>
                                <td class="px-3 py-2" data-label="Action">
                                    <x-ui.row-actions>
                                        <form method="POST" action="{{ route('admin.server.database.restore', ['filename' => $backup['filename']]) }}" data-sm-confirm="Rollback the live database to this backup? This will overwrite current data. Make sure you have a current backup first." data-sm-confirm-button="Rollback Database">
                                            @csrf
                                            <x-ui.row-action label="Rollback database to this backup" icon="fa-solid fa-rotate-left" tone="neutral" type="submit" aria-label="Rollback database to this backup" />
                                        </form>
                                        <x-ui.row-action label="Download" icon="fa-solid fa-download" tone="neutral" href="{{ route('admin.server.database.download', ['filename' => $backup['filename']]) }}" download="{{ $backup['filename'] }}" />
                                        <form method="POST" action="{{ route('admin.server.database.delete', ['filename' => $backup['filename']]) }}" data-sm-confirm="Delete this backup file? This cannot be undone." data-sm-confirm-button="Delete Backup">
                                            @csrf
                                            @method('DELETE')
                                            <x-ui.row-action label="Delete" icon="fa-solid fa-trash" tone="danger" type="submit" />
                                        </form>
                                    </x-ui.row-actions>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
                <div class="mt-3">
                    <x-ui.list-pagination :paginator="$databaseBackups" />
                </div>
            @endif
            </x-ui.dynamic-list>
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <div class="flex flex-wrap items-center justify-between gap-3 mb-3">
                <h3 class="text-lg font-bold">File Backups</h3>
                <div class="flex items-center gap-3">
                    <form method="POST" action="{{ route('admin.server.files.backup-now') }}" @submit.prevent="startBackup($event, 'files')">
                        @csrf
                        <x-ui.button type="submit" color="dark" x-bind:disabled="isRunning('files')">Full Backup Now</x-ui.button>
                    </form>
                </div>
            </div>


            <p class="text-xs text-gray-600 mb-3">Monthly full backups are scheduled via <code>files:backup --full</code> and nightly incrementals via <code>files:backup --incremental --window=24h</code>. When <code>--keep</code> is omitted, retention uses <code>backup.files.full.keep</code> (currently {{ number_format((int) $fileBackupFullKeepCount) }} runs) and <code>backup.files.incremental.keep</code> (currently {{ number_format((int) $fileBackupIncrementalKeepCount) }} runs). File backups are written to <code>/storage/backups/files</code> for offsite sync and restore.</p>

            <div
                id="file-backup-import"
                class="mb-5 rounded-lg border border-gray-200 bg-slate-50 p-4"
                data-start-url="{{ route('admin.server.files.imports.start') }}"
                data-status-url="{{ route('admin.server.files.imports.status', ['uploadId' => '__UPLOAD_ID__']) }}"
                data-cancel-url="{{ route('admin.server.files.imports.cancel', ['uploadId' => '__UPLOAD_ID__']) }}"
                data-chunk-url="{{ route('admin.server.files.imports.chunk', ['uploadId' => '__UPLOAD_ID__']) }}"
                data-finish-url="{{ route('admin.server.files.imports.finish', ['uploadId' => '__UPLOAD_ID__']) }}"
                data-chunk-size="{{ \App\Services\FileBackupUploadService::CHUNK_SIZE }}"
            >
                <h4 class="font-semibold text-gray-900">Upload a file backup to restore</h4>
                <p class="mt-1 text-xs text-gray-600">Choose a ZIP created by the File Backups archive action. Large archives upload in resumable chunks. The archive is validated and added to this list; no files are restored until you review and confirm selected items. The origin must accept request bodies of at least 9 MiB.</p>
                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="min-w-0 flex-1">
                        <label for="file-backup-archive" class="mb-1 block text-sm font-medium text-gray-800">File backup archive (.zip)</label>
                        <input id="file-backup-archive" type="file" accept=".zip,application/zip" class="block w-full text-sm text-gray-700 file:mr-3 file:rounded-md file:border-0 file:bg-white file:px-3 file:py-2 file:font-semibold file:text-gray-800 file:shadow-sm file:ring-1 file:ring-gray-300 hover:file:bg-gray-100" />
                    </div>
                    <x-ui.button id="file-backup-upload-button" type="button" color="dark" disabled>Upload and validate</x-ui.button>
                </div>
                <div id="file-backup-upload-progress" class="hidden mt-4 rounded-lg border border-sky-200 bg-sky-50 p-3" role="status" aria-live="polite">
                    <div class="flex items-start justify-between gap-3">
                        <p id="file-backup-upload-message" class="text-sm font-semibold text-sky-950">Preparing upload…</p>
                        <span id="file-backup-upload-percent" class="shrink-0 text-xs font-semibold text-sky-800">0%</span>
                    </div>
                    <div id="file-backup-upload-track" class="mt-2 h-2.5 overflow-hidden rounded-full bg-white" role="progressbar" aria-label="File backup upload" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                        <div id="file-backup-upload-bar" class="h-full rounded-full bg-sky-600 transition-[width] duration-200" style="width: 0%"></div>
                    </div>
                    <p id="file-backup-upload-detail" class="mt-2 text-xs text-sky-800">If the connection stops, select the same file again to resume from the last saved chunk.</p>
                    <button id="file-backup-upload-cancel" type="button" class="hidden mt-3 text-xs font-semibold text-red-700 hover:underline">Discard staged upload</button>
                </div>
            </div>

            @if($fileBackups->isEmpty())
                <p class="text-sm text-gray-600">No file backups found yet.</p>
            @else
                <div class="overflow-auto border border-gray-200 rounded-lg">
                    <x-ui.table variant="plain" table-class="sm-backup-table w-full text-sm">
                        <thead class="bg-gray-50">
                        <tr>
                            <x-ui.list-heading class="text-left px-3 py-2" label="Run" />
                            <x-ui.list-heading class="text-center px-3 py-2" label="Mode" />
                            <x-ui.list-heading class="px-3 py-2 text-center!" label="Created" />
                            <x-ui.list-heading class="text-center px-3 py-2" label="Files" />
                            <x-ui.list-heading class="text-center px-3 py-2" label="Deleted" />
                            <x-ui.list-heading class="px-3 py-2 text-center!" label="Size" />
                            <x-ui.list-heading class="text-center! px-3 py-2" label="Actions" />
                        </tr>
                        </thead>
                        <tbody>
                        @foreach($fileBackups as $backup)
                            @php($fileBackupReadable = (bool) ($backup['is_readable'] ?? true))
                            <tr class="border-t border-gray-100">
                                <td class="px-3 py-2 font-mono text-xs break-all" data-label="Run">
                                    @if($fileBackupReadable)
                                    <a class="text-primary-color hover:underline" href="{{ route('admin.server.files.show', ['mode' => $backup['mode'], 'filename' => $backup['filename']]) }}">
                                        {{ $backup['filename'] }}
                                    </a>
                                    @else
                                    <span class="text-gray-700">{{ $backup['filename'] }}</span>
                                    <div class="mt-1 text-xs text-red-700">Unreadable by the web user. Check storage permissions.</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center" data-label="Mode">
                                    <x-ui.badge :color="(string) $backup['mode'] === \App\Services\FileBackupService::MODE_INCREMENTAL ? 'warning' : 'sky'">
                                        {{ ucfirst((string) $backup['mode']) }}
                                    </x-ui.badge>
                                    @if(! empty($backup['window_hours']))
                                        <div class="mt-1 text-xs text-gray-500">{{ number_format((int) $backup['window_hours']) }}h window</div>
                                    @endif
                                </td>
                                <td class="px-3 py-2 text-center!" data-label="Created">
                                    <x-ui.date-time>{{ trim((string) ($backup['created_at'] ?? $backup['modified_at'] ?? '')) !== '' ? \Carbon\Carbon::parse((string) ($backup['created_at'] ?? $backup['modified_at']))->format('Y-m-d H:i:s') : '-' }}</x-ui.date-time>
                                </td>
                                <td class="px-3 py-2 text-center" data-label="Files">{{ number_format((int) $backup['uploaded_files']) }}</td>
                                <td class="px-3 py-2 text-center" data-label="Deleted">{{ number_format((int) $backup['deleted_files']) }}</td>
                                <td class="px-3 py-2 text-center!" data-label="Size"><x-ui.nonbreaking>{{ \App\Helpers::bytesToString((int) $backup['size']) }}</x-ui.nonbreaking></td>
                                <td class="text-center! px-3 py-2" data-label="Action">
                                    @if($fileBackupReadable)
                                    <x-ui.row-action label="View files" icon="fa-solid fa-folder-open" tone="neutral" href="{{ route('admin.server.files.show', ['mode' => $backup['mode'], 'filename' => $backup['filename']]) }}" />
                                    @else
                                    <span class="text-gray-400" title="Backup run is not readable">
                                        <i class="fa-solid fa-folder-open"></i>
                                    </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </x-ui.table>
                </div>
            @endif
        </div>

        <div class="my-4 bg-white border border-gray-200 rounded-lg shadow-sm p-4">
            <h3 class="text-lg font-bold mb-3">Bulk File Download</h3>
            <x-ui.grid class="md:grid-cols-2 gap-4">
                <div class="rounded-lg border border-gray-200 bg-white p-3 flex justify-between">
                    <div>
                        <div class="font-semibold mb-1">Media Files</div>
                        <div class="text-xs text-gray-600 mb-3">
                            {{ number_format((int) ($mediaStats['count'] ?? 0)) }} files
                            •
                            {{ \App\Helpers::bytesToString((int) ($mediaStats['size'] ?? 0)) }}
                        </div>
                    </div>
                    <form method="POST" action="{{ route('admin.server.media.download-all') }}" @submit.prevent="startBackup($event, 'media_archive')">
                        @csrf
                        <x-ui.button variant="plain" type="submit" x-bind:disabled="isRunning('media_archive')" class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-50" title="Prepare media ZIP" aria-label="Prepare media ZIP"><i class="fa-solid fa-download"></i></x-ui.button>
                    </form>
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
                    <form method="POST" action="{{ route('admin.server.finance.download-all') }}" @submit.prevent="startBackup($event, 'finance_archive')">
                        @csrf
                        <x-ui.button variant="plain" type="submit" x-bind:disabled="isRunning('finance_archive')" class="inline-flex h-10 w-10 items-center justify-center rounded-md border border-gray-400 bg-white text-gray-800 shadow-sm transition hover:bg-gray-500 hover:text-white disabled:cursor-not-allowed disabled:opacity-50" title="Prepare finance ZIP" aria-label="Prepare finance ZIP"><i class="fa-solid fa-download"></i></x-ui.button>
                    </form>
                </div>
            </x-ui.grid>
        </div>
        </div>
    </x-container>
</x-layout>

<script nonce="{{ \Illuminate\Support\Facades\Vite::cspNonce() }}">
    window.serverBackupRunner = (config) => ({
        runs: Array.isArray(config.initialRuns) ? config.initialRuns : [],
        timers: {},

        init() {
            this.runs.filter((run) => !run.finished).forEach((run) => this.poll(run.id));
            window.addEventListener('sm:server-backup-run-queued', (event) => {
                const run = event.detail;
                if (!run || !run.id) return;
                this.upsert(run);
                this.poll(run.id);
            });
        },

        isRunning(type) {
            return this.runs.some((run) => run.type === type && !run.finished);
        },

        operationLabel(type) {
            return ({ database: 'Database backup', files: 'Full file backup', file_import: 'File backup import', media_archive: 'Media files download', finance_archive: 'Finance files download', backup_archive: 'Backup download' })[type] || 'Server operation';
        },

        async startBackup(event, type) {
            if (this.isRunning(type)) return;

            const form = event.target;
            const response = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form),
            });
            const payload = await response.json();
            if (!response.ok || !payload.run) {
                this.runs.unshift({ id: `${type}-error`, type, status: 'failed', progress: 100, finished: true, message: '', error_message: payload.message || 'Unable to queue backup.' });
                return;
            }

            this.upsert(payload.run);
            this.poll(payload.run.id);
        },

        upsert(run) {
            const index = this.runs.findIndex((item) => item.id === run.id);
            if (index === -1) this.runs.unshift(run);
            else this.runs.splice(index, 1, run);
        },

        poll(runId) {
            window.clearTimeout(this.timers[runId]);
            this.timers[runId] = window.setTimeout(async () => {
                try {
                    const url = config.statusUrl.replace('__RUN_ID__', encodeURIComponent(runId));
                    const response = await fetch(url, { headers: { 'Accept': 'application/json' } });
                    const payload = await response.json();
                    if (!response.ok || !payload.run) throw new Error('Unable to read backup status.');
                    this.upsert(payload.run);
                    if (payload.run.finished) {
                        if (payload.run.status === 'completed' && payload.run.download_url) {
                            window.location.assign(payload.run.download_url);
                            window.setTimeout(() => { this.runs = this.runs.filter((run) => run.id !== runId); }, 5000);
                        } else {
                            window.setTimeout(() => window.location.reload(), 4000);
                        }
                    } else {
                        this.poll(runId);
                    }
                } catch (error) {
                    this.timers[runId] = window.setTimeout(() => this.poll(runId), 5000);
                }
            }, 1500);
        },
    });

    const initServerBackupControls = () => {
        const databaseImportInput = document.getElementById('database_backup');
        const databaseImportForm = document.getElementById('database-import-form');
        const databaseImportProgress = document.getElementById('database-import-progress');
        const databaseImportProgressMessage = document.getElementById('database-import-progress-message');
        const databaseImportProgressPercent = document.getElementById('database-import-progress-percent');
        const databaseImportProgressDetail = document.getElementById('database-import-progress-detail');
        const databaseImportProgressTrack = document.getElementById('database-import-progress-track');
        const databaseImportProgressBar = document.getElementById('database-import-progress-bar');
        const databaseImportReload = document.getElementById('database-import-reload');
        const fileBackupImport = document.getElementById('file-backup-import');
        const fileBackupInput = document.getElementById('file-backup-archive');
        const fileBackupUploadButton = document.getElementById('file-backup-upload-button');
        const fileBackupCancelButton = document.getElementById('file-backup-upload-cancel');
        const fileBackupProgress = document.getElementById('file-backup-upload-progress');
        const fileBackupProgressMessage = document.getElementById('file-backup-upload-message');
        const fileBackupProgressPercent = document.getElementById('file-backup-upload-percent');
        const fileBackupProgressDetail = document.getElementById('file-backup-upload-detail');
        const fileBackupProgressTrack = document.getElementById('file-backup-upload-track');
        const fileBackupProgressBar = document.getElementById('file-backup-upload-bar');
        let databaseImportInProgress = false;
        let databaseImportStartedAt = 0;
        let databaseImportElapsedTimer = null;
        let fileBackupUploadInProgress = false;
        let fileBackupQueued = false;
        let activeFileBackupUploadId = null;

        const formatElapsed = (milliseconds) => {
            const seconds = Math.max(0, Math.floor(milliseconds / 1000));
            const minutes = Math.floor(seconds / 60);
            return minutes > 0 ? `${minutes}:${String(seconds % 60).padStart(2, '0')}` : `${seconds}s`;
        };

        const showImportFailure = (message) => {
            window.clearInterval(databaseImportElapsedTimer);
            databaseImportInProgress = false;
            databaseImportProgressMessage.textContent = 'The import result could not be confirmed.';
            databaseImportProgressDetail.textContent = message;
            databaseImportReload.classList.remove('hidden');
            databaseImportInput.disabled = false;
            databaseImportInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: false } }));
        };

        const startDatabaseImport = () => {
            if (databaseImportInProgress || !databaseImportInput.files || databaseImportInput.files.length === 0) {
                return;
            }

            const formData = new FormData(databaseImportForm);
            const xhr = new XMLHttpRequest();
            databaseImportInProgress = true;
            databaseImportStartedAt = Date.now();
            databaseImportProgress.classList.remove('hidden');
            databaseImportReload.classList.add('hidden');
            databaseImportProgressMessage.textContent = 'Uploading backup…';
            databaseImportProgressDetail.textContent = 'Keep this page open while the backup is uploaded.';
            databaseImportProgressPercent.hidden = false;
            databaseImportProgressPercent.textContent = '0%';
            databaseImportProgressTrack.setAttribute('aria-label', 'Database backup upload');
            databaseImportProgressTrack.setAttribute('aria-valuenow', '0');
            databaseImportProgressTrack.removeAttribute('aria-valuetext');
            databaseImportProgressBar.style.width = '0%';
            databaseImportProgressBar.classList.remove('animate-pulse');
            databaseImportInput.disabled = true;

            const showRestoringState = () => {
                if (databaseImportProgressMessage.textContent.startsWith('Restoring database')) {
                    return;
                }

                databaseImportProgressMessage.textContent = 'Restoring database…';
                databaseImportProgressPercent.hidden = true;
                databaseImportProgressDetail.textContent = 'The upload is complete. MySQL is restoring the database; this can take several minutes.';
                databaseImportProgressTrack.setAttribute('aria-label', 'Database restore progress');
                databaseImportProgressTrack.removeAttribute('aria-valuenow');
                databaseImportProgressTrack.setAttribute('aria-valuetext', 'Restore in progress; percentage unavailable');
                databaseImportProgressBar.style.width = '40%';
                databaseImportProgressBar.classList.add('animate-pulse');
                databaseImportInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: false } }));
                databaseImportElapsedTimer = window.setInterval(() => {
                    const elapsed = formatElapsed(Date.now() - databaseImportStartedAt);
                    databaseImportProgressMessage.textContent = `Restoring database… ${elapsed}`;
                }, 10000);
            };

            xhr.open('POST', databaseImportForm.action, true);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'text/html,application/xhtml+xml');
            xhr.upload.addEventListener('progress', (event) => {
                if (!event.lengthComputable || event.total <= 0) {
                    return;
                }

                const percent = Math.min(100, Math.round((event.loaded / event.total) * 100));
                databaseImportProgressPercent.textContent = `${percent}%`;
                databaseImportProgressTrack.setAttribute('aria-valuenow', String(percent));
                databaseImportProgressBar.style.width = `${percent}%`;
            });
            xhr.upload.addEventListener('load', showRestoringState);
            xhr.addEventListener('load', () => {
                window.clearInterval(databaseImportElapsedTimer);
                if (xhr.status >= 200 && xhr.status < 400) {
                    databaseImportProgressMessage.textContent = 'Import finished. Refreshing…';
                    window.location.reload();
                    return;
                }

                showImportFailure(`The server returned HTTP ${xhr.status}. Reload this page to check the result before trying again.`);
            });
            xhr.addEventListener('error', () => {
                showImportFailure('The connection was interrupted. Reload this page to check whether the import completed before trying again.');
            });
            xhr.addEventListener('abort', () => {
                showImportFailure('The request was stopped. Reload this page to check the result before trying again.');
            });
            xhr.send(formData);
        };

        const fileBackupFormatBytes = (bytes) => {
            const units = ['B', 'KB', 'MB', 'GB', 'TB'];
            let amount = Math.max(0, Number(bytes) || 0);
            let unit = 0;
            while (amount >= 1024 && unit < units.length - 1) {
                amount /= 1024;
                unit++;
            }
            return `${amount.toLocaleString(undefined, { maximumFractionDigits: unit === 0 ? 0 : 1 })} ${units[unit]}`;
        };

        const fileBackupSetProgress = (message, detail, uploaded = null, total = null, indeterminate = false) => {
            fileBackupProgress.classList.remove('hidden');
            fileBackupProgressMessage.textContent = message;
            fileBackupProgressDetail.textContent = detail;
            fileBackupProgressBar.classList.toggle('animate-pulse', indeterminate);
            if (indeterminate) {
                fileBackupProgressPercent.hidden = true;
                fileBackupProgressTrack.removeAttribute('aria-valuenow');
                fileBackupProgressTrack.setAttribute('aria-valuetext', 'File backup validation is in progress');
                fileBackupProgressBar.style.width = '40%';
                return;
            }

            const percent = total > 0 ? Math.min(100, Math.round((uploaded / total) * 100)) : 0;
            fileBackupProgressPercent.hidden = false;
            fileBackupProgressPercent.textContent = `${percent}%`;
            fileBackupProgressTrack.setAttribute('aria-valuenow', String(percent));
            fileBackupProgressTrack.removeAttribute('aria-valuetext');
            fileBackupProgressBar.style.width = `${percent}%`;
        };

        const fileBackupRequest = async (url, method = 'GET', payload = null) => {
            const headers = { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            if (csrf) headers['X-CSRF-TOKEN'] = csrf;
            if (payload !== null) headers['Content-Type'] = 'application/json';
            const response = await fetch(url, {
                method,
                headers,
                body: payload === null ? undefined : JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));
            if (!response.ok) {
                const error = new Error(data.message || `The server returned HTTP ${response.status}.`);
                error.status = response.status;
                throw error;
            }
            return data;
        };

        const clearFileBackupUploadReference = (uploadId) => {
            const prefix = 'stemmechanics:file-backup-upload:v1:';
            for (let index = localStorage.length - 1; index >= 0; index--) {
                const key = localStorage.key(index);
                if (!key || !key.startsWith(prefix)) continue;
                try {
                    if (JSON.parse(localStorage.getItem(key) || 'null')?.upload_id === uploadId) {
                        localStorage.removeItem(key);
                    }
                } catch (error) {
                    localStorage.removeItem(key);
                }
            }
        };

        const fileBackupFingerprint = async (file) => {
            const edgeBytes = 256 * 1024;
            const first = new Uint8Array(await file.slice(0, Math.min(edgeBytes, file.size)).arrayBuffer());
            const lastStart = Math.max(0, file.size - edgeBytes);
            const last = new Uint8Array(await file.slice(lastStart, file.size).arrayBuffer());
            const sample = new Uint8Array(first.length + last.length);
            sample.set(first, 0);
            sample.set(last, first.length);

            if (window.crypto?.subtle) {
                const digest = new Uint8Array(await window.crypto.subtle.digest('SHA-256', sample));
                return `sha256:${Array.from(digest, (byte) => byte.toString(16).padStart(2, '0')).join('')}`;
            }

            let hash = 2166136261;
            sample.forEach((byte) => {
                hash ^= byte;
                hash = Math.imul(hash, 16777619);
            });
            return `sample:${(hash >>> 0).toString(16).padStart(8, '0')}:${file.size}:${file.lastModified}`;
        };

        const uploadFileBackupChunk = (url, uploadId, file, start, end, onProgress) => new Promise((resolve, reject) => {
            const xhr = new XMLHttpRequest();
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            xhr.open('POST', url, true);
            xhr.timeout = 10 * 60 * 1000;
            xhr.setRequestHeader('Accept', 'application/json');
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Content-Type', 'application/octet-stream');
            xhr.setRequestHeader('Content-Range', `bytes ${start}-${end - 1}/${file.size}`);
            if (csrf) xhr.setRequestHeader('X-CSRF-TOKEN', csrf);
            xhr.upload.addEventListener('progress', (event) => {
                if (event.lengthComputable) onProgress(Math.min(file.size, start + event.loaded));
            });
            xhr.addEventListener('load', () => {
                let payload = {};
                try { payload = JSON.parse(xhr.responseText || '{}'); } catch (error) {}
                if (xhr.status >= 200 && xhr.status < 300 && payload.upload) {
                    resolve(payload.upload);
                } else {
                    reject(new Error(payload.message || `The server returned HTTP ${xhr.status}.`));
                }
            });
            xhr.addEventListener('error', () => reject(new Error('The connection was interrupted while sending a chunk.')));
            xhr.addEventListener('timeout', () => reject(new Error('This upload chunk timed out. Select the same file to resume.')));
            xhr.addEventListener('abort', () => reject(new Error('The upload chunk was stopped.')));
            xhr.send(file.slice(start, end));
        });

        const startFileBackupUpload = async () => {
            if (fileBackupUploadInProgress || !fileBackupInput.files || fileBackupInput.files.length === 0) return;
            const file = fileBackupInput.files[0];
            if (!file.name.toLowerCase().endsWith('.zip')) {
                fileBackupSetProgress('Choose a ZIP file.', 'Only ZIP archives created by the File Backups archive action can be imported.');
                return;
            }

            fileBackupUploadInProgress = true;
            fileBackupInput.disabled = true;
            fileBackupUploadButton.disabled = true;
            fileBackupCancelButton.disabled = true;
            fileBackupInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: true } }));
            fileBackupSetProgress('Preparing upload…', 'Checking available space and looking for a previous upload to resume.', 0, file.size);

            const localKeyPrefix = 'stemmechanics:file-backup-upload:v1:';
            let localKey = null;
            try {
                const fingerprint = await fileBackupFingerprint(file);
                localKey = `${localKeyPrefix}${fingerprint}:${file.size}`;
                const statusUrl = (uploadId) => fileBackupImport.dataset.statusUrl.replace('__UPLOAD_ID__', encodeURIComponent(uploadId));
                const routeUrl = (template, uploadId) => template.replace('__UPLOAD_ID__', encodeURIComponent(uploadId));
                let upload = null;
                let saved = null;
                try { saved = JSON.parse(localStorage.getItem(localKey) || 'null'); } catch (error) {}

                if (saved?.upload_id) {
                    try {
                        const response = await fileBackupRequest(statusUrl(saved.upload_id));
                        const candidate = response.upload;
                        if (candidate && candidate.fingerprint === fingerprint && Number(candidate.size) === file.size && candidate.filename === file.name && ['uploading', 'queued', 'completed'].includes(candidate.status)) {
                            upload = candidate;
                            activeFileBackupUploadId = candidate.status === 'uploading' ? candidate.upload_id : null;
                        } else {
                            localStorage.removeItem(localKey);
                        }
                    } catch (error) {
                        if (error.status === 404) localStorage.removeItem(localKey);
                        else throw error;
                    }
                }

                if (!upload) {
                    const response = await fileBackupRequest(fileBackupImport.dataset.startUrl, 'POST', {
                        filename: file.name,
                        size: file.size,
                        fingerprint,
                    });
                    upload = response.upload;
                    localStorage.setItem(localKey, JSON.stringify({ upload_id: upload.upload_id }));
                    activeFileBackupUploadId = upload.upload_id;
                }

                fileBackupCancelButton.classList.toggle('hidden', !activeFileBackupUploadId);

                const finishUrl = routeUrl(fileBackupImport.dataset.finishUrl, upload.upload_id);
                if (!['queued', 'completed'].includes(upload.status)) {
                    const chunkUrl = routeUrl(fileBackupImport.dataset.chunkUrl, upload.upload_id);
                    const chunkSize = Number(upload.chunk_size || fileBackupImport.dataset.chunkSize || 8 * 1024 * 1024);
                    let received = Number(upload.received_bytes || 0);

                    while (received < file.size) {
                        const start = received;
                        const end = Math.min(file.size, start + chunkSize);
                        const detail = `${fileBackupFormatBytes(start)} of ${fileBackupFormatBytes(file.size)} uploaded. Keep this page open; you can resume later by selecting the same archive.`;
                        fileBackupSetProgress(`Uploading file backup… ${Math.round((start / file.size) * 100)}%`, detail, start, file.size);
                        let result = null;
                        let lastError = null;
                        for (let attempt = 0; attempt < 3 && !result; attempt++) {
                            try {
                                result = await uploadFileBackupChunk(chunkUrl, upload.upload_id, file, start, end, (progressBytes) => {
                                    fileBackupSetProgress(
                                        `Uploading file backup… ${Math.round((progressBytes / file.size) * 100)}%`,
                                        `${fileBackupFormatBytes(progressBytes)} of ${fileBackupFormatBytes(file.size)} uploaded. Keep this page open; you can resume later by selecting the same archive.`,
                                        progressBytes,
                                        file.size
                                    );
                                });
                            } catch (error) {
                                lastError = error;
                                await new Promise((resolve) => window.setTimeout(resolve, 700 * (attempt + 1)));
                            }
                        }
                        if (!result) throw lastError || new Error('The upload chunk could not be sent.');
                        upload = result;
                        received = Number(upload.received_bytes || 0);
                        localStorage.setItem(localKey, JSON.stringify({ upload_id: upload.upload_id }));
                    }
                }

                fileBackupSetProgress('Upload complete. Validating archive…', 'The server is checking the ZIP contents and available storage before adding it to the backup list.', null, null, true);
                const queued = await fileBackupRequest(finishUrl, 'POST', {});
                localStorage.removeItem(localKey);
                activeFileBackupUploadId = null;
                fileBackupQueued = true;
                fileBackupCancelButton.classList.add('hidden');
                fileBackupSetProgress('Archive queued for validation.', 'It will appear in File Backups when validation finishes. Review the files there and confirm any restore separately.', null, null, true);
                fileBackupInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: false } }));
                window.dispatchEvent(new CustomEvent('sm:server-backup-run-queued', { detail: queued.run }));
            } catch (error) {
                const detail = localKey ? 'Your saved chunks are kept temporarily. Select the same file and choose Upload and validate to resume.' : 'Choose the archive again to retry.';
                let receivedBytes = 0;
                if (localKey && file.size > 0) {
                    try {
                        const saved = JSON.parse(localStorage.getItem(localKey) || 'null');
                        if (saved?.upload_id) {
                            activeFileBackupUploadId = saved.upload_id;
                            const statusUrl = fileBackupImport.dataset.statusUrl.replace('__UPLOAD_ID__', encodeURIComponent(saved.upload_id));
                            const status = await fileBackupRequest(statusUrl);
                            receivedBytes = Number(status.upload?.received_bytes || 0);
                            if (['queued', 'completed'].includes(status.upload?.status)) activeFileBackupUploadId = null;
                        }
                    } catch (statusError) {}
                }
                fileBackupCancelButton.classList.toggle('hidden', !activeFileBackupUploadId || fileBackupQueued);
                fileBackupSetProgress('The file backup upload could not continue.', `${error?.message || 'The request failed.'} ${detail}`, receivedBytes, file.size);
                fileBackupInput.dispatchEvent(new CustomEvent('sm:file-upload-state', { detail: { uploading: false } }));
            } finally {
                fileBackupUploadInProgress = false;
                fileBackupInput.disabled = false;
                fileBackupUploadButton.disabled = fileBackupQueued || !fileBackupInput.files || fileBackupInput.files.length === 0;
                fileBackupCancelButton.disabled = fileBackupUploadInProgress;
            }
        };

        const discardFileBackupUpload = async () => {
            if (!activeFileBackupUploadId || fileBackupUploadInProgress) return;
            fileBackupCancelButton.disabled = true;
            try {
                const cancelUrl = fileBackupImport.dataset.cancelUrl.replace('__UPLOAD_ID__', encodeURIComponent(activeFileBackupUploadId));
                await fileBackupRequest(cancelUrl, 'POST', {});
                clearFileBackupUploadReference(activeFileBackupUploadId);
                activeFileBackupUploadId = null;
                fileBackupCancelButton.classList.add('hidden');
                fileBackupSetProgress('Staged upload discarded.', 'Select a file backup archive to start another upload.');
                fileBackupInput.value = '';
                fileBackupUploadButton.disabled = true;
            } catch (error) {
                fileBackupSetProgress('The staged upload could not be discarded.', error?.message || 'Try again shortly.');
            } finally {
                fileBackupCancelButton.disabled = false;
            }
        };

        if (fileBackupInput instanceof HTMLInputElement && fileBackupUploadButton instanceof HTMLButtonElement) {
            fileBackupInput.addEventListener('change', () => {
                fileBackupUploadButton.disabled = fileBackupQueued || !fileBackupInput.files || fileBackupInput.files.length === 0 || fileBackupUploadInProgress;
            });
            fileBackupUploadButton.addEventListener('click', startFileBackupUpload);
            fileBackupCancelButton.addEventListener('click', discardFileBackupUpload);
        }

        if (databaseImportReload instanceof HTMLButtonElement) {
            databaseImportReload.addEventListener('click', () => window.location.reload());
        }

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
            databaseImportForm.addEventListener('submit', (event) => {
                if (databaseImportForm.dataset.confirmedSubmit !== '1') {
                    return;
                }

                event.preventDefault();
                startDatabaseImport();
            });

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
