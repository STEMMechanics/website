<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Models\AuditLog;
use App\Models\Expense;
use App\Models\Media;
use App\Models\Payment;
use App\Models\SentEmail;
use App\Models\SquareIgnoredPayment;
use App\Models\SquareWebhookEvent;
use App\Services\DatabaseBackupService;
use App\Services\SquareWebhookSyncService;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Process\Process;

class ServerController extends Controller
{
    private const ORPHAN_SCAN_REPORT_FILE = 'server/orphaned-files-report.json';

    public function __construct(
        private readonly DatabaseBackupService $databaseBackupService
    ) {}

    public function admin_index(): View
    {
        $logPath = $this->getLaravelLogPath();
        $logData = $this->getFileData($logPath, 300);
        $deployLogPath = $this->getDeployOutputPath();
        $deployLogData = $this->getFileData($deployLogPath, 150);
        $databaseBackups = $this->paginateBackups(request());

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
            'databaseBackups' => $databaseBackups,
            'mediaStats' => $this->directoryStats('media', '/'),
            'financeStats' => $this->directoryStats('local', 'finance'),
        ]);
    }

    public function admin_database_export(Request $request)
    {
        try {
            $path = $this->databaseBackupService->createBackup();
            $keep = max(1, (int) config('backup.database.keep', 168));
            $this->databaseBackupService->pruneOldBackups($keep);
        } catch (\Throwable $e) {
            session()->flash('message', 'Database export failed: '.$e->getMessage());
            session()->flash('message-title', 'Export failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.index');
        }

        $filename = basename($path);

        return response()->download($path, $filename, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    public function admin_database_backup_now(Request $request): RedirectResponse
    {
        try {
            $path = $this->databaseBackupService->createBackup();
            $keep = max(1, (int) config('backup.database.keep', 168));
            $this->databaseBackupService->pruneOldBackups($keep);
        } catch (\Throwable $e) {
            session()->flash('database_backup_notice', [
                'type' => 'danger',
                'text' => 'Backup failed: '.$e->getMessage(),
            ]);

            return redirect()->route('admin.server.index');
        }

        session()->flash('database_backup_notice', [
            'type' => 'success',
            'text' => 'Backup created: '.basename($path),
        ]);

        return redirect()->route('admin.server.index');
    }

    public function admin_database_download(string $filename)
    {
        $safeFilename = basename($filename);
        if (! Str::endsWith($safeFilename, '.sql.gz')) {
            abort(404);
        }

        $path = $this->databaseBackupService->backupPath($safeFilename);
        if (! is_file($path)) {
            abort(404);
        }

        return response()->download($path, $safeFilename, [
            'Content-Type' => 'application/gzip',
        ]);
    }

    public function admin_database_delete(string $filename): RedirectResponse
    {
        $safeFilename = basename($filename);
        if (! Str::endsWith($safeFilename, '.sql.gz')) {
            abort(404);
        }

        $path = $this->databaseBackupService->backupPath($safeFilename);
        if (! is_file($path)) {
            abort(404);
        }

        if (! @unlink($path)) {
            session()->flash('message', 'Could not delete backup file: '.$safeFilename);
            session()->flash('message-title', 'Delete failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.index');
        }

        session()->flash('message', 'Backup file deleted: '.$safeFilename);
        session()->flash('message-title', 'Backup deleted');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.server.index');
    }

    public function admin_database_import(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'database_backup' => ['required', 'file', 'max:512000'], // 500 MB
        ]);

        $file = $validated['database_backup'];
        $originalName = strtolower((string) $file->getClientOriginalName());
        if (! (Str::endsWith($originalName, '.sql') || Str::endsWith($originalName, '.sql.gz'))) {
            return back()->withErrors([
                'database_backup' => 'Please upload a .sql or .sql.gz database backup file.',
            ]);
        }

        $tempPath = null;

        try {
            $storedRelativePath = $file->storeAs(
                'server/database-imports',
                now()->format('Ymd_His').'_'.Str::random(8).'_'.preg_replace('/[^a-zA-Z0-9._-]/', '-', (string) $file->getClientOriginalName())
            );

            if (! is_string($storedRelativePath) || $storedRelativePath === '') {
                throw new \RuntimeException('Upload failed.');
            }

            $tempPath = Storage::disk('local')->path($storedRelativePath);
            $this->databaseBackupService->restoreBackup($tempPath);
            Storage::disk('local')->delete($storedRelativePath);

            session()->flash('message', 'Database import completed successfully.');
            session()->flash('message-title', 'Import complete');
            session()->flash('message-type', 'success');
        } catch (\Throwable $e) {
            if (is_string($tempPath) && is_file($tempPath)) {
                @unlink($tempPath);
            }

            session()->flash('message', 'Database import failed: '.$e->getMessage());
            session()->flash('message-title', 'Import failed');
            session()->flash('message-type', 'danger');
        }

        return redirect()->route('admin.server.index');
    }

    public function admin_media_download_all()
    {
        return $this->streamDiskDirectoryAsZip('media', '/', 'media-files');
    }

    public function admin_finance_download_all()
    {
        return $this->streamDiskDirectoryAsZip('local', 'finance', 'finance-files');
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

        if (! $this->isCommandAvailable('zip')) {
            session()->flash('message', 'The `zip` command is not available on this server.');
            session()->flash('message-title', 'Download unavailable');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.orphans');
        }

        $filename = 'orphaned-files-'.$scope.'-'.now()->format('Ymd-His').'.zip';

        return $this->streamArbitraryFilesAsZip($files, $filename);
    }

    public function admin_orphans_delete_file(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'disk' => ['required', 'in:local,media'],
            'path' => ['required', 'string', 'max:2000'],
        ]);

        $report = $this->getOrphanScanReport();
        if (! is_array($report)) {
            session()->flash('message', 'Run an orphan scan first.');
            session()->flash('message-title', 'No scan results');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.orphans');
        }

        $disk = (string) $validated['disk'];
        $path = ltrim(trim((string) $validated['path']), '/');
        if ($path === '' || str_contains($path, '..')) {
            abort(403);
        }

        $orphanFiles = collect($this->resolveOrphanDownloadFiles($report, 'orphan_all'));
        $isOrphanPath = $orphanFiles->contains(fn (array $entry) => (string) ($entry['disk'] ?? '') === $disk && (string) ($entry['path'] ?? '') === $path);
        if (! $isOrphanPath) {
            session()->flash('message', 'The selected file is not in the current orphan list.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.orphans');
        }

        if (! Storage::disk($disk)->exists($path)) {
            session()->flash('message', 'File does not exist anymore.');
            session()->flash('message-title', 'Already removed');
            session()->flash('message-type', 'warning');
            $this->refreshOrphanScanReport();

            return redirect()->route('admin.server.orphans');
        }

        $deleted = Storage::disk($disk)->delete($path);
        $this->refreshOrphanScanReport();

        if (! $deleted) {
            session()->flash('message', 'Unable to delete orphan file.');
            session()->flash('message-title', 'Delete failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.orphans');
        }

        session()->flash('message', 'Orphan file deleted: '.$path);
        session()->flash('message-title', 'File deleted');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.server.orphans');
    }

    public function admin_orphans_delete_all(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'in:orphan_all,orphan_expense,orphan_media'],
        ]);

        $report = $this->getOrphanScanReport();
        if (! is_array($report)) {
            session()->flash('message', 'Run an orphan scan first.');
            session()->flash('message-title', 'No scan results');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.orphans');
        }

        $scope = (string) $validated['scope'];
        $files = $this->resolveOrphanDownloadFiles($report, $scope);
        if ($files === []) {
            session()->flash('message', 'No orphan files found for this scope.');
            session()->flash('message-title', 'Nothing to delete');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.orphans');
        }

        $deleted = 0;
        foreach ($files as $entry) {
            $disk = (string) ($entry['disk'] ?? '');
            $path = ltrim((string) ($entry['path'] ?? ''), '/');
            if (! in_array($disk, ['local', 'media'], true) || $path === '' || str_contains($path, '..')) {
                continue;
            }

            if (Storage::disk($disk)->exists($path) && Storage::disk($disk)->delete($path)) {
                $deleted++;
            }
        }

        $this->refreshOrphanScanReport();

        session()->flash('message', 'Deleted '.number_format($deleted).' orphan file(s).');
        session()->flash('message-title', 'Bulk delete complete');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.server.orphans');
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

        $seedEvents = $query
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        $groupedEvents = collect();
        $seenKeys = [];
        foreach ($seedEvents as $seedEvent) {
            $seedPayload = is_array($seedEvent->payload) ? $seedEvent->payload : null;
            $squarePaymentId = $this->extractSquarePaymentIdFromPayload($seedPayload);
            $groupKey = $squarePaymentId !== '' ? 'pid:'.$squarePaymentId : 'event:'.$seedEvent->id;
            if (isset($seenKeys[$groupKey])) {
                continue;
            }
            $seenKeys[$groupKey] = true;

            if ($squarePaymentId === '') {
                $groupEvents = collect([$seedEvent]);
            } else {
                $groupEvents = SquareWebhookEvent::query()
                    ->with('customerPayment')
                    ->where(function ($builder) use ($squarePaymentId): void {
                        $builder->where('payload->data->object->payment->id', $squarePaymentId)
                            ->orWhere('payload->data->object->refund->payment_id', $squarePaymentId);
                    })
                    ->orderByDesc('processed_at')
                    ->orderByDesc('id')
                    ->get();
            }

            if ($groupEvents->isEmpty()) {
                continue;
            }

            $groupedEvents->push($groupEvents->values());
        }

        $events = $groupedEvents->flatten(1)->values();
        $squarePaymentIds = $events
            ->map(fn (SquareWebhookEvent $event): string => $this->extractSquarePaymentIdFromPayload(is_array($event->payload) ? $event->payload : null))
            ->filter(fn (string $id): bool => $id !== '')
            ->unique()
            ->values();
        $ignoredIds = SquareIgnoredPayment::query()
            ->whereIn('square_payment_id', $squarePaymentIds->all())
            ->pluck('square_payment_id')
            ->all();
        $ignoredLookup = array_fill_keys($ignoredIds, true);

        $events = $events->map(function (SquareWebhookEvent $event) use ($ignoredLookup) {
            $squarePaymentId = $this->extractSquarePaymentIdFromPayload(is_array($event->payload) ? $event->payload : null);
            $event->setAttribute('square_payment_id', $squarePaymentId);
            $event->setAttribute('is_ignored', $squarePaymentId !== '' && isset($ignoredLookup[$squarePaymentId]));
            $event->setAttribute('amount_cents', $this->extractSquareAmountCentsFromPayload(is_array($event->payload) ? $event->payload : null));
            $event->setAttribute('amount_currency', $this->extractSquareAmountCurrencyFromPayload(is_array($event->payload) ? $event->payload : null));

            return $event;
        });

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
            'ignoreReasonOptions' => $this->squareIgnoreReasonOptions(),
        ]);
    }

    public function admin_square_webhooks_sync(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'only_unlinked' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $onlyUnlinked = (bool) ($validated['only_unlinked'] ?? true);
        $limit = max(0, (int) ($validated['limit'] ?? 0));

        $params = [];
        if ($onlyUnlinked) {
            $params['--only-unlinked'] = true;
        }
        if ($limit > 0) {
            $params['--limit'] = $limit;
        }

        $exitCode = Artisan::call('square:webhooks:sync', $params);
        $output = trim((string) Artisan::output());
        $summary = $output !== '' ? $output : 'Square webhook sync finished.';

        session()->flash('message', $summary);
        session()->flash('message-title', $exitCode === 0 ? 'Square webhook sync complete' : 'Square webhook sync finished with errors');
        session()->flash('message-type', $exitCode === 0 ? 'success' : 'warning');

        return redirect()->route('admin.server.square-webhooks', $request->only(['search', 'event_type']));
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
        $squarePaymentId = $this->extractSquarePaymentIdFromPayload(is_array($event->payload) ? $event->payload : null);
        $ignoredRecord = $squarePaymentId !== ''
            ? SquareIgnoredPayment::query()->where('square_payment_id', $squarePaymentId)->first()
            : null;

        return view('admin.server.square-webhook-show', [
            'event' => $event,
            'payloadPretty' => json_encode($event->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'squarePaymentId' => $squarePaymentId,
            'ignoredRecord' => $ignoredRecord,
            'ignoreReasonOptions' => $this->squareIgnoreReasonOptions(),
        ]);
    }

    public function admin_square_webhook_ignore(Request $request, SquareWebhookEvent $event): RedirectResponse
    {
        $validated = $request->validate([
            'reason_code' => ['required', 'string', Rule::in(array_keys($this->squareIgnoreReasonOptions()))],
            'reason_other' => ['nullable', 'string', 'max:1000'],
        ]);

        $payload = is_array($event->payload) ? $event->payload : [];
        $squarePaymentId = $this->extractSquarePaymentIdFromPayload($payload);
        if ($squarePaymentId === '') {
            session()->flash('message', 'This webhook does not contain a Square payment ID to ignore.');
            session()->flash('message-title', 'Ignore failed');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $reason = $this->resolveSquareIgnoreReason($validated);
        if ($reason === null) {
            session()->flash('message', 'A reason is required to ignore this payment.');
            session()->flash('message-title', 'Ignore failed');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        $linkedPayment = Payment::query()
            ->where('square_integration_meta->square_payment_id', $squarePaymentId)
            ->first();

        $deletedLinkedPaymentId = null;
        if ($linkedPayment instanceof Payment) {
            if (! $this->canDeleteIgnoredAutoImportedPayment($linkedPayment)) {
                session()->flash('message', 'Cannot ignore Square payment '.$squarePaymentId.' because linked payment #'.$linkedPayment->id.' has financial links (or is not an auto-imported unallocated POS payment).');
                session()->flash('message-title', 'Ignore blocked');
                session()->flash('message-type', 'danger');

                return redirect()->back();
            }

            $deletedLinkedPaymentId = (int) $linkedPayment->id;
            $linkedPayment->delete();
        }

        SquareIgnoredPayment::query()->updateOrCreate(
            ['square_payment_id' => $squarePaymentId],
            [
                'reason' => $reason,
                'created_by' => Auth::id(),
            ]
        );

        $relatedEvents = $this->relatedSquareWebhookEvents($squarePaymentId);
        foreach ($relatedEvents as $relatedEvent) {
            if ($relatedEvent->payment_id !== null) {
                $relatedEvent->payment_id = null;
                $relatedEvent->save();
            }
        }

        $message = 'Square payment '.$squarePaymentId.' is now ignored for future sync runs.';
        if ($deletedLinkedPaymentId !== null) {
            $message .= ' Auto-imported payment #'.$deletedLinkedPaymentId.' was removed.';
        }
        session()->flash('message', $message);
        session()->flash('message-title', 'Ignore rule saved');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function admin_square_webhook_unignore(SquareWebhookEvent $event, SquareWebhookSyncService $syncService): RedirectResponse
    {
        $payload = is_array($event->payload) ? $event->payload : [];
        $squarePaymentId = $this->extractSquarePaymentIdFromPayload($payload);
        if ($squarePaymentId === '') {
            session()->flash('message', 'This webhook does not contain a Square payment ID.');
            session()->flash('message-title', 'Unignore failed');
            session()->flash('message-type', 'warning');

            return redirect()->back();
        }

        SquareIgnoredPayment::query()->where('square_payment_id', $squarePaymentId)->delete();

        $relatedEvents = $this->relatedSquareWebhookEvents($squarePaymentId);
        $processed = 0;
        $errors = 0;
        foreach ($relatedEvents as $relatedEvent) {
            try {
                $syncService->syncPayload(is_array($relatedEvent->payload) ? $relatedEvent->payload : [], $relatedEvent);
                $processed++;
            } catch (\Throwable) {
                $errors++;
            }
        }

        $message = 'Ignore rule removed for Square payment '.$squarePaymentId.'.';
        if ($processed > 0) {
            $message .= ' Reprocessed '.$processed.' related webhook event(s).';
        }
        if ($errors > 0) {
            $message .= ' '.$errors.' event(s) failed while reprocessing.';
        }

        session()->flash('message', $message);
        session()->flash('message-title', $errors === 0 ? 'Ignore rule removed' : 'Ignore removed with warnings');
        session()->flash('message-type', $errors === 0 ? 'success' : 'warning');

        return redirect()->back();
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

    /**
     * @param array<string, mixed>|null $payload
     */
    private function extractSquarePaymentIdFromPayload(?array $payload): string
    {
        if (! is_array($payload)) {
            return '';
        }

        return trim((string) (
            data_get($payload, 'data.object.payment.id')
            ?? data_get($payload, 'data.object.refund.payment_id')
            ?? ''
        ));
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function extractSquareAmountCentsFromPayload(?array $payload): ?int
    {
        if (! is_array($payload)) {
            return null;
        }

        $refundAmount = data_get($payload, 'data.object.refund.amount_money.amount');
        if ($refundAmount !== null && is_numeric($refundAmount)) {
            return 0 - (int) $refundAmount;
        }

        $paymentAmount = data_get($payload, 'data.object.payment.amount_money.amount');
        if ($paymentAmount !== null && is_numeric($paymentAmount)) {
            return (int) $paymentAmount;
        }

        return null;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function extractSquareAmountCurrencyFromPayload(?array $payload): string
    {
        if (! is_array($payload)) {
            return '';
        }

        $currency = data_get($payload, 'data.object.refund.amount_money.currency')
            ?? data_get($payload, 'data.object.payment.amount_money.currency')
            ?? '';

        return trim((string) $currency);
    }

    private function canDeleteIgnoredAutoImportedPayment(\App\Models\Payment $payment): bool
    {
        if (! $payment->isAutoImportedSquarePos()) {
            return false;
        }

        if ($payment->allocations()->exists()) {
            return false;
        }

        if ($payment->refunds()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function squareIgnoreReasonOptions(): array
    {
        return [
            'wrong_business' => 'Transaction belongs to another business',
            'test_transaction' => 'Square test/training transaction',
            'duplicate_terminal_charge' => 'Duplicate terminal charge',
            'reconciled_elsewhere' => 'Reconciled outside STEMMechanics',
            'other' => 'Other',
        ];
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function resolveSquareIgnoreReason(array $validated): ?string
    {
        $reasonCode = trim((string) ($validated['reason_code'] ?? ''));
        if ($reasonCode === '') {
            return null;
        }

        $options = $this->squareIgnoreReasonOptions();
        if (! array_key_exists($reasonCode, $options)) {
            return null;
        }

        if ($reasonCode === 'other') {
            $other = trim((string) ($validated['reason_other'] ?? ''));

            return $other === '' ? null : 'Other: '.$other;
        }

        return $options[$reasonCode];
    }

    /**
     * @return \Illuminate\Support\Collection<int, SquareWebhookEvent>
     */
    private function relatedSquareWebhookEvents(string $squarePaymentId)
    {
        return SquareWebhookEvent::query()
            ->with('customerPayment')
            ->where(function ($builder) use ($squarePaymentId): void {
                $builder->where('payload->data->object->payment->id', $squarePaymentId)
                    ->orWhere('payload->data->object->refund->payment_id', $squarePaymentId);
            })
            ->orderByDesc('processed_at')
            ->orderByDesc('id')
            ->get();
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

    /**
     * @return array{count: int, size: int}
     */
    private function directoryStats(string $disk, string $prefix): array
    {
        $count = 0;
        $size = 0;
        $normalizedPrefix = trim($prefix, '/');
        $searchPrefix = $normalizedPrefix === '' ? '/' : $normalizedPrefix;

        $files = Storage::disk($disk)->allFiles($searchPrefix);
        foreach ($files as $path) {
            $path = ltrim((string) $path, '/');
            if ($path === '') {
                continue;
            }

            $count++;
            try {
                $size += (int) Storage::disk($disk)->size($path);
            } catch (\Throwable $e) {
                // Ignore per-file stat errors so we still report count and partial size.
            }
        }

        return [
            'count' => $count,
            'size' => $size,
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

    private function streamDiskDirectoryAsZip(string $disk, string $prefix, string $archivePrefix)
    {
        $normalizedPrefix = trim($prefix, '/');
        $searchPrefix = $normalizedPrefix === '' ? '/' : $normalizedPrefix;
        $files = collect(Storage::disk($disk)->allFiles($searchPrefix))
            ->map(fn ($path) => ltrim((string) $path, '/'))
            ->filter(fn ($path) => $path !== '')
            ->values()
            ->all();

        if ($files === []) {
            session()->flash('message', 'No files found for this archive.');
            session()->flash('message-title', 'Nothing to download');
            session()->flash('message-type', 'warning');

            return redirect()->route('admin.server.index');
        }

        $rootPath = $normalizedPrefix === ''
            ? Storage::disk($disk)->path('/')
            : Storage::disk($disk)->path($normalizedPrefix);

        if (! is_dir($rootPath)) {
            session()->flash('message', 'Archive directory is missing on disk.');
            session()->flash('message-title', 'Download failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.index');
        }

        if (! $this->isCommandAvailable('zip')) {
            session()->flash('message', 'The `zip` command is not available on this server.');
            session()->flash('message-title', 'Download failed');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.server.index');
        }

        $filename = $archivePrefix.'-'.now()->format('Ymd-His').'.zip';

        return response()->streamDownload(function () use ($rootPath): void {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $process = new Process(['zip', '-qr', '-', '.'], $rootPath, null, null, null);
            $process->run(function (string $type, string $buffer): void {
                if ($type === Process::OUT) {
                    echo $buffer;
                    if (function_exists('ob_flush')) {
                        @ob_flush();
                    }
                    flush();
                }
            });

            if (! $process->isSuccessful()) {
                throw new \RuntimeException('Failed to stream ZIP archive.');
            }
        }, $filename, [
            'Content-Type' => 'application/zip',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function isCommandAvailable(string $command): bool
    {
        $process = Process::fromShellCommandline('command -v '.escapeshellarg($command));
        $process->setTimeout(10);
        $process->run();

        return $process->isSuccessful();
    }

    /**
     * @param array<int, array{disk: string, path: string}> $files
     */
    private function streamArbitraryFilesAsZip(array $files, string $filename): StreamedResponse
    {
        return response()->streamDownload(function () use ($files): void {
            @set_time_limit(0);
            @ini_set('output_buffering', 'off');
            @ini_set('zlib.output_compression', '0');

            $stageBase = tempnam(sys_get_temp_dir(), 'orphans-stage-');
            if (! is_string($stageBase)) {
                throw new \RuntimeException('Unable to allocate temporary staging directory.');
            }

            @unlink($stageBase);
            if (! @mkdir($stageBase, 0775, true) && ! is_dir($stageBase)) {
                throw new \RuntimeException('Unable to create temporary staging directory.');
            }

            try {
                foreach ($files as $entry) {
                    $disk = (string) $entry['disk'];
                    $path = ltrim((string) $entry['path'], '/');
                    if (! in_array($disk, ['local', 'media'], true) || $path === '') {
                        continue;
                    }

                    if (! Storage::disk($disk)->exists($path)) {
                        continue;
                    }

                    $sourcePath = Storage::disk($disk)->path($path);
                    if (! is_file($sourcePath)) {
                        continue;
                    }

                    $targetPath = $stageBase.'/'.$disk.'/'.$path;
                    $targetDir = dirname($targetPath);
                    if (! is_dir($targetDir)) {
                        @mkdir($targetDir, 0775, true);
                    }

                    if (! @symlink($sourcePath, $targetPath)) {
                        @copy($sourcePath, $targetPath);
                    }
                }

                $process = new Process(['zip', '-qr', '-', '.'], $stageBase, null, null, null);
                $process->run(function (string $type, string $buffer): void {
                    if ($type === Process::OUT) {
                        echo $buffer;
                        if (function_exists('ob_flush')) {
                            @ob_flush();
                        }
                        flush();
                    }
                });

                if (! $process->isSuccessful()) {
                    throw new \RuntimeException('Failed to stream ZIP archive.');
                }
            } finally {
                $this->deleteDirectoryRecursive($stageBase);
            }
        }, $filename, [
            'Content-Type' => 'application/zip',
            'X-Accel-Buffering' => 'no',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function deleteDirectoryRecursive(string $directory): void
    {
        if (! is_dir($directory)) {
            return;
        }

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );

            foreach ($iterator as $item) {
                if ($item->isDir() && ! $item->isLink()) {
                    @rmdir($item->getPathname());
                    continue;
                }

                @unlink($item->getPathname());
            }
        } catch (\Throwable $e) {
            // Best-effort cleanup only.
        }

        @rmdir($directory);
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

    private function refreshOrphanScanReport(): void
    {
        $report = $this->buildOrphanScanReport();
        Storage::disk('local')->put(self::ORPHAN_SCAN_REPORT_FILE, json_encode($report, JSON_PRETTY_PRINT));
    }

    private function paginateBackups(Request $request): LengthAwarePaginator
    {
        $allBackups = collect($this->databaseBackupService->listBackups())->values();
        $perPage = 20;
        $page = max(1, (int) $request->query('backup_page', 1));
        $items = $allBackups->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $allBackups->count(),
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'pageName' => 'backup_page',
                'query' => $request->query(),
            ]
        );
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
