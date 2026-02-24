<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Media;
use App\Models\SentEmail;
use App\Models\SquareWebhookEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ZipArchive;

class ServerController extends Controller
{
    private const ORPHAN_SCAN_REPORT_FILE = 'server/orphaned-files-report.json';

    public function admin_index(): View
    {
        $logPath = $this->getLaravelLogPath();
        $logData = $this->getFileData($logPath, 300);
        $deployLogPath = $this->getDeployOutputPath();
        $deployLogData = $this->getFileData($deployLogPath, 150);
        return view('admin.server.index', [
            'serverInfo' => $this->getServerInfo(),
            'logPath' => $logPath,
            'logExists' => $logData['exists'],
            'logSize' => $logData['size'],
            'logModifiedAt' => $logData['modified_at'],
            'logContent' => $logData['content'],
            'deployOutputPath' => $deployLogPath,
            'deployOutputExists' => $deployLogData['exists'],
            'deployOutputModifiedAt' => $deployLogData['modified_at'],
            'deployOutputContent' => $deployLogData['content'],
        ]);
    }

    public function admin_clear_log(): RedirectResponse
    {
        $logPath = $this->getLaravelLogPath();

        if (!file_exists($logPath)) {
            // Create the file to keep behavior predictable for the log viewer.
            file_put_contents($logPath, '');
        } else {
            file_put_contents($logPath, '');
        }

        session()->flash('message', 'laravel.log has been cleared');
        session()->flash('message-title', 'Log cleared');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.server.index');
    }

    public function admin_clear_deploy_log(): RedirectResponse
    {
        $logPath = $this->getDeployOutputPath();

        if (!file_exists($logPath)) {
            file_put_contents($logPath, '');
        } else {
            file_put_contents($logPath, '');
        }

        session()->flash('message', 'deploy.log has been cleared');
        session()->flash('message-title', 'Deploy log cleared');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.server.index');
    }

    public function admin_deploy(Request $request): RedirectResponse
    {
        $args = [];
        $label = [];
        if ($request->boolean('current')) {
            $args[] = '--current';
            $label[] = 'current';
        } else {
            $label[] = 'release';
        }
        if ($request->boolean('force')) {
            $args[] = '--force';
            $label[] = 'force';
        }

        return $this->startDeployProcess($args, 'Deploy started (' . implode(', ', $label) . ')');
    }

    public function admin_deploy_log(): JsonResponse
    {
        $deployLogPath = $this->getDeployOutputPath();
        $deployLogData = $this->getFileData($deployLogPath, 150);

        return response()->json([
            'exists' => $deployLogData['exists'],
            'modified_at' => $deployLogData['modified_at'],
            'content' => $deployLogData['content'],
        ]);
    }

    public function admin_laravel_log(): JsonResponse
    {
        $laravelLogPath = $this->getLaravelLogPath();
        $laravelLogData = $this->getFileData($laravelLogPath, 300);

        return response()->json([
            'exists' => $laravelLogData['exists'],
            'size' => $laravelLogData['size'],
            'modified_at' => $laravelLogData['modified_at'],
            'content' => $laravelLogData['content'],
        ]);
    }

    public function admin_orphans(): View
    {
        return view('admin.server.orphans', [
            'report' => $this->getOrphanScanReport(),
        ]);
    }

    public function admin_orphans_scan(): RedirectResponse
    {
        $report = $this->buildOrphanScanReport();
        Storage::disk('local')->put(self::ORPHAN_SCAN_REPORT_FILE, json_encode($report, JSON_PRETTY_PRINT));

        session()->flash('message', 'Orphan file scan completed');
        session()->flash('message-title', 'Scan complete');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.server.orphans');
    }

    public function admin_orphans_file(Request $request): SymfonyResponse
    {
        $diskName = (string) $request->query('disk', '');
        $path = ltrim((string) $request->query('path', ''), '/');
        $download = $request->boolean('download');

        if (! in_array($diskName, ['local', 'media'], true) || $path === '') {
            abort(404, 'File not found');
        }

        if (! Storage::disk($diskName)->exists($path)) {
            abort(404, 'File not found');
        }

        $absolutePath = Storage::disk($diskName)->path($path);
        $realPath = realpath($absolutePath);
        $rootPath = realpath(Storage::disk($diskName)->path('/'));
        if ($realPath === false || $rootPath === false || ! str_starts_with($realPath, rtrim($rootPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)) {
            abort(403);
        }

        $filename = basename($path);

        if ($download) {
            return response()->download($realPath, $filename);
        }

        return response()->file($realPath, [
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    public function admin_orphans_download_all(Request $request)
    {
        $report = $this->getOrphanScanReport();
        if (! is_array($report)) {
            session()->flash('message', 'Run an orphan scan first.');
            session()->flash('message-title', 'No scan results');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.orphans');
        }

        $scope = (string) $request->query('scope', 'orphan_all');
        $files = $this->resolveOrphanDownloadFiles($report, $scope);
        if ($files === []) {
            session()->flash('message', 'No files available for this download scope.');
            session()->flash('message-title', 'Nothing to download');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.orphans');
        }

        if (! class_exists(ZipArchive::class)) {
            session()->flash('message', 'ZIP extension is not enabled on this server.');
            session()->flash('message-title', 'Download unavailable');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.orphans');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'orphans-');
        if (! is_string($zipPath)) {
            session()->flash('message', 'Unable to create temporary archive file.');
            session()->flash('message-title', 'Download failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.orphans');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            session()->flash('message', 'Unable to create ZIP archive.');
            session()->flash('message-title', 'Download failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.orphans');
        }

        foreach ($files as $entry) {
            $diskName = $entry['disk'];
            $path = $entry['path'];
            if (! Storage::disk($diskName)->exists($path)) {
                continue;
            }

            $absolutePath = Storage::disk($diskName)->path($path);
            $archivePath = $diskName.'/'.$path;
            $zip->addFile($absolutePath, $archivePath);
        }

        $zip->close();

        $filename = 'orphaned-files-'.$scope.'-'.now()->format('Ymd-His').'.zip';

        return response()->download($zipPath, $filename)->deleteFileAfterSend(true);
    }

    public function admin_square_webhooks(Request $request): View
    {
        $query = SquareWebhookEvent::query()->with('customerPayment');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('event_id', 'like', '%'.$search.'%')
                    ->orWhere('event_type', 'like', '%'.$search.'%')
                    ->orWhere('payment_id', 'like', '%'.$search.'%');
            });
        }

        if ($request->filled('event_type')) {
            $eventType = trim((string) $request->query('event_type', ''));
            if ($eventType !== '') {
                $query->where('event_type', $eventType);
            }
        }

        $events = $query->orderByDesc('processed_at')->orderByDesc('id')->paginate(25)->onEachSide(1);

        $eventTypes = SquareWebhookEvent::query()
            ->select('event_type')
            ->whereNotNull('event_type')
            ->groupBy('event_type')
            ->orderBy('event_type')
            ->pluck('event_type')
            ->all();

        return view('admin.server.square-webhooks', [
            'events' => $events,
            'eventTypes' => $eventTypes,
        ]);
    }

    public function admin_sent_emails(Request $request): View
    {
        $query = SentEmail::query();

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('id', 'like', '%'.$search.'%')
                    ->orWhere('recipient', 'like', '%'.$search.'%')
                    ->orWhere('mailable_class', 'like', '%'.$search.'%')
                    ->orWhere('error_message', 'like', '%'.$search.'%');
            });
        }

        $status = trim((string) $request->query('status', ''));
        if ($status !== '') {
            $query->where('status', $status);
        }

        $emails = $query
            ->orderByDesc('created_at')
            ->paginate(50)
            ->onEachSide(1);

        return view('admin.server.sent-emails', [
            'emails' => $emails,
            'statuses' => [
                SentEmail::STATUS_QUEUED,
                SentEmail::STATUS_SENT,
                SentEmail::STATUS_FAILED,
            ],
        ]);
    }

    public function admin_audit(Request $request): View
    {
        $this->authorize('viewAny', AuditLog::class);

        $query = AuditLog::query()->with('actor');

        $search = trim((string) $request->query('search', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder->where('auditable_type', 'like', '%'.$search.'%')
                    ->orWhere('auditable_id', 'like', '%'.$search.'%')
                    ->orWhere('event', 'like', '%'.$search.'%')
                    ->orWhere('ip_address', 'like', '%'.$search.'%')
                    ->orWhere('url', 'like', '%'.$search.'%')
                    ->orWhereHas('actor', function ($actorQuery) use ($search): void {
                        $actorQuery->where('email', 'like', '%'.$search.'%')
                            ->orWhere('firstname', 'like', '%'.$search.'%')
                            ->orWhere('surname', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('event')) {
            $event = trim((string) $request->query('event', ''));
            if ($event !== '') {
                $query->where('event', $event);
            }
        }

        $logs = $query->orderByDesc('created_at')->paginate(50)->onEachSide(1);
        $totalRecords = AuditLog::query()->count();
        $oldestRecordAt = AuditLog::query()->min('created_at');
        $tableSizeBytes = $this->auditLogTableSizeBytes();
        $tableSizeHuman = $tableSizeBytes !== null ? Helpers::bytesToString($tableSizeBytes) : null;
        $events = AuditLog::query()
            ->select('event')
            ->whereNotNull('event')
            ->groupBy('event')
            ->orderBy('event')
            ->pluck('event')
            ->all();

        return view('admin.server.audit', [
            'logs' => $logs,
            'events' => $events,
            'auditMeta' => [
                'table_size_bytes' => $tableSizeBytes,
                'table_size_human' => $tableSizeHuman,
                'oldest_record_at' => $oldestRecordAt,
                'total_records' => $totalRecords,
            ],
        ]);
    }

    public function admin_audit_prune(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', AuditLog::class);

        $validated = $request->validate([
            'prune_days' => ['required', 'integer', 'min:7', 'max:3650'],
            'event' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $pruneDays = (int) $validated['prune_days'];
        $cutoff = now()->subDays($pruneDays)->startOfDay();
        $deleted = AuditLog::query()
            ->where('created_at', '<', $cutoff)
            ->delete();

        session()->flash('message', 'Pruned '.number_format((int) $deleted).' audit records older than '.$pruneDays.' days.');
        session()->flash('message-title', 'Audit logs pruned');
        session()->flash('message-type', 'success');

        $redirectParams = [];
        if (trim((string) ($validated['event'] ?? '')) !== '') {
            $redirectParams['event'] = trim((string) $validated['event']);
        }
        if (trim((string) ($validated['search'] ?? '')) !== '') {
            $redirectParams['search'] = trim((string) $validated['search']);
        }

        return redirect()->route('admin.server.audit', $redirectParams);
    }

    public function admin_square_webhook_show(SquareWebhookEvent $event): View
    {
        $event->loadMissing('customerPayment');

        return view('admin.server.square-webhook-show', [
            'event' => $event,
            'payloadPretty' => json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
        ]);
    }

    private function startDeployProcess(array $args, string $successTitle): RedirectResponse
    {
        $scriptPath = $this->getDeployScriptPath();
        $outputPath = $this->getDeployOutputPath();

        if (!function_exists('exec')) {
            session()->flash('message', 'Cannot run deploy script: PHP exec() is disabled');
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        if (!is_file($scriptPath)) {
            session()->flash('message', "Deploy script not found at $scriptPath");
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        if (!is_executable($scriptPath)) {
            session()->flash('message', "Deploy script is not executable: $scriptPath");
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $argString = '';
        foreach ($args as $arg) {
            $argString .= ' ' . escapeshellarg($arg);
        }

        $deployCommand = escapeshellarg($scriptPath) . $argString;
        $timestampedCommand = $deployCommand . " 2>&1 | awk '{ print strftime(\"[%Y-%m-%d %H:%M:%S]\"), \$0; fflush(); }'";

        @file_put_contents(
            $outputPath,
            '[' . date('Y-m-d H:i:s') . '] Starting deploy command: ' . $deployCommand . PHP_EOL .
            '[' . date('Y-m-d H:i:s') . '] Updates may be delayed while the deploy script runs. Please wait and refresh the page after a few moments.' . PHP_EOL
        );

        $command = sprintf(
            'nohup bash -lc %s >> %s 2>&1 & echo $!',
            escapeshellarg($timestampedCommand),
            escapeshellarg($outputPath)
        );

        $output = [];
        $status = 0;
        @exec($command, $output, $status);

        if ($status !== 0) {
            session()->flash('message', "Failed to start deploy script (exit $status). Check permissions for the web user.");
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        $pid = trim((string) ($output[0] ?? ''));
        session()->flash('message', 'Deploy script started in background' . ($pid !== '' ? " (PID: $pid)" : '') . '.');
        session()->flash('message-title', $successTitle);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.server.index');
    }

    private function auditLogTableSizeBytes(): ?int
    {
        $driver = DB::getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $size = DB::table('information_schema.tables')
                ->where('table_schema', DB::getDatabaseName())
                ->where('table_name', 'audit_logs')
                ->selectRaw('COALESCE(data_length, 0) + COALESCE(index_length, 0) AS total_bytes')
                ->value('total_bytes');

            return is_numeric($size) ? (int) $size : null;
        }

        if ($driver === 'pgsql') {
            $row = DB::selectOne("SELECT pg_total_relation_size('audit_logs') AS total_bytes");

            return is_object($row) && isset($row->total_bytes) && is_numeric($row->total_bytes)
                ? (int) $row->total_bytes
                : null;
        }

        return null;
    }

    private function getServerInfo(): array
    {
        $rootPath = '/';
        $storagePublicPath = storage_path('app');
        $diskFree = @disk_free_space($rootPath);

        return [
            'App Environment' => app()->environment(),
            'App Version' => config('app.version'),
            'App Commit' => (config('app.commit') ?: 'N/A'),
            'Laravel Version' => app()->version(),
            'PHP Version' => PHP_VERSION,
            'PHP SAPI' => PHP_SAPI,
            'Web Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'Operating System' => php_uname(),
            'Server Time' => date('Y-m-d H:i:s'),
            'IP Address' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
            'Timezone' => config('app.timezone'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'Post Max Size' => ini_get('post_max_size'),
            'PHP INI File' => php_ini_loaded_file() ?: 'Unknown',
            'OPcache Enabled' => extension_loaded('Zend OPcache') ? 'Yes' : 'No',
            'Disk Free Space' => is_numeric($diskFree) ? $this->formatBytes((int) $diskFree) : 'N/A',
            'Storage Usage (storage/app)' => is_dir($storagePublicPath)
                ? $this->formatBytes($this->getDirectorySize($storagePublicPath))
                : 'N/A',
            'Loaded Extensions' => implode(', ', get_loaded_extensions()),
        ];
    }

    private function getLaravelLogPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    private function getDeployScriptPath(): string
    {
        return (string) config('services.deploy.script_path', '/app/deploy.sh');
    }

    private function getDeployOutputPath(): string
    {
        return (string) config('services.deploy.output_log', '/var/tmp/stemmechanics_deploy.log');
    }

    private function getFileData(string $path, int $tailLines): array
    {
        $exists = file_exists($path);

        return [
            'exists' => $exists,
            'size' => $exists ? filesize($path) : 0,
            'modified_at' => $exists ? date('Y-m-d H:i:s', filemtime($path)) : null,
            'content' => $exists ? $this->tailFile($path, $tailLines) : '',
        ];
    }

    private function tailFile(string $path, int $lineCount = 300): string
    {
        $lines = [];
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $lineCount);

        $file->seek($start);
        while (!$file->eof()) {
            $lines[] = rtrim((string) $file->current(), "\r\n");
            $file->next();
        }

        return trim(implode(PHP_EOL, $lines));
    }

    private function getDirectorySize(string $directory): int
    {
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $value = $bytes / 1024;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, 2) . ' ' . $units[$index];
    }

    private function getOrphanScanReport(): ?array
    {
        $disk = Storage::disk('local');
        if (! $disk->exists(self::ORPHAN_SCAN_REPORT_FILE)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get(self::ORPHAN_SCAN_REPORT_FILE), true);

        return is_array($decoded) ? $decoded : null;
    }

    private function buildOrphanScanReport(): array
    {
        $scannedAt = now()->toDateTimeString();

        $expenseRecords = Expense::query()
            ->whereNotNull('receipt_document_path')
            ->get(['id', 'receipt_document_path']);

        $expensePathMap = [];
        foreach ($expenseRecords as $expense) {
            $path = trim((string) ($expense->receipt_document_path ?? ''));
            if ($path === '') {
                continue;
            }
            $expensePathMap[$path] ??= [];
            $expensePathMap[$path][] = (int) $expense->id;
        }

        $expensePaths = collect(array_keys($expensePathMap))
            ->filter()
            ->values()
            ->all();

        $allExpenseFiles = collect(Storage::disk('local')->allFiles('finance/expenses'))
            ->map(fn ($path) => trim((string) $path))
            ->reject(fn ($path) => $this->shouldIgnoreOrphanScanPath($path))
            ->filter()
            ->values();

        $orphanExpenseFiles = $allExpenseFiles
            ->filter(fn ($path) => ! in_array($path, $expensePaths, true))
            ->values()
            ->all();

        $missingExpenseFiles = [];
        foreach ($expensePaths as $path) {
            if (Storage::disk('local')->exists($path)) {
                continue;
            }

            $missingExpenseFiles[] = [
                'path' => $path,
                'expense_ids' => array_values(array_unique($expensePathMap[$path] ?? [])),
            ];
        }

        $mediaRecords = Media::query()->get(['name', 'hash', 'variants']);
        $referencedMediaFiles = [];
        foreach ($mediaRecords as $media) {
            $recordKey = (string) $media->getKey();
            $name = trim((string) $media->name);
            $hash = trim((string) ($media->hash ?? ''));

            if ($hash !== '') {
                $referencedMediaFiles[$hash] ??= [];
                $referencedMediaFiles[$hash][] = ['media_name' => $recordKey, 'source' => 'hash'];
            } elseif ($name !== '') {
                // Legacy fallback: old rows may reference a physical filename directly.
                $referencedMediaFiles[$name] ??= [];
                $referencedMediaFiles[$name][] = ['media_name' => $recordKey, 'source' => 'name'];
            }

            $variants = is_array($media->variants) ? $media->variants : [];
            foreach ($variants as $variantName => $variantData) {
                if ($hash !== '' && trim((string) $variantName) !== '') {
                    $variantPath = $hash.'-'.$variantName;
                    $referencedMediaFiles[$variantPath] ??= [];
                    $referencedMediaFiles[$variantPath][] = ['media_name' => $recordKey, 'source' => 'variant:'.$variantName];
                }
            }
        }

        $allMediaFiles = collect(Storage::disk('media')->allFiles('/'))
            ->map(fn ($path) => ltrim(trim((string) $path), '/'))
            ->reject(fn ($path) => $this->shouldIgnoreOrphanScanPath($path))
            ->filter()
            ->values();

        $orphanMediaFiles = $allMediaFiles
            ->filter(fn ($path) => empty($referencedMediaFiles[$path]))
            ->values()
            ->all();

        $missingMediaFiles = [];
        foreach ($referencedMediaFiles as $path => $references) {
            if (Storage::disk('media')->exists($path)) {
                continue;
            }

            $missingMediaFiles[] = [
                'path' => (string) $path,
                'references' => $references,
            ];
        }

        return [
            'scanned_at' => $scannedAt,
            'summary' => [
                'expense_files_scanned' => $allExpenseFiles->count(),
                'expense_files_referenced' => count($expensePaths),
                'orphan_expense_files' => count($orphanExpenseFiles),
                'missing_expense_files' => count($missingExpenseFiles),
                'media_files_scanned' => $allMediaFiles->count(),
                'media_files_referenced' => count($referencedMediaFiles),
                'orphan_media_files' => count($orphanMediaFiles),
                'missing_media_files' => count($missingMediaFiles),
            ],
            'orphans' => [
                'expense_files' => $orphanExpenseFiles,
                'media_files' => $orphanMediaFiles,
            ],
            'missing_references' => [
                'expense_files' => $missingExpenseFiles,
                'media_files' => $missingMediaFiles,
            ],
        ];
    }

    private function resolveOrphanDownloadFiles(array $report, string $scope): array
    {
        $orphans = $report['orphans'] ?? [];
        $expenseOrphans = collect($orphans['expense_files'] ?? [])->map(function ($entry): ?array {
            if (! is_string($entry) || trim($entry) === '') {
                return null;
            }

            return ['disk' => 'local', 'path' => ltrim(trim($entry), '/')];
        })->filter()->values()->all();

        $mediaOrphans = collect($orphans['media_files'] ?? [])->map(function ($entry): ?array {
            if (! is_string($entry) || trim($entry) === '') {
                return null;
            }

            return ['disk' => 'media', 'path' => ltrim(trim($entry), '/')];
        })->filter()->values()->all();

        return match ($scope) {
            'orphan_expense' => $expenseOrphans,
            'orphan_media' => $mediaOrphans,
            default => array_merge($expenseOrphans, $mediaOrphans),
        };
    }

    private function shouldIgnoreOrphanScanPath(string $path): bool
    {
        $basename = basename($path);

        return in_array($basename, [
            '.DS_Store',
            'Thumbs.db',
            'desktop.ini',
        ], true);
    }
}
