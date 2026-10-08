<?php

namespace App\Jobs;

use App\Models\ServerBackupRun;
use App\Services\DatabaseBackupService;
use App\Services\FileBackupService;
use App\Services\FileBackupImportService;
use App\Services\FileBackupUploadService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class RunServerBackup implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 3600;

    public function __construct(public string $runId) {}

    public function handle(
        DatabaseBackupService $databases,
        FileBackupService $files,
        FileBackupImportService $fileImports,
        FileBackupUploadService $fileUploads
    ): void
    {
        $claimed = ServerBackupRun::query()
            ->whereKey($this->runId)
            ->where('status', ServerBackupRun::STATUS_QUEUED)
            ->update([
                'status' => ServerBackupRun::STATUS_RUNNING,
                'progress' => 15,
                'message' => 'Starting backup operation…',
                'started_at' => now(),
            ]);
        if ($claimed !== 1) {
            return;
        }

        $run = ServerBackupRun::query()->findOrFail($this->runId);
        $request = is_array($run->result) ? $run->result : [];
        $run->update(['message' => $this->runningMessage($run->type)]);

        if ($run->type === ServerBackupRun::TYPE_DATABASE) {
            $path = $databases->createBackup();
            $run->update(['progress' => 90, 'message' => 'Applying backup retention…']);
            $databases->pruneOldBackups($databases->resolvedKeepCount());
            $result = ['filename' => basename($path)];
            $message = 'Database backup created: '.basename($path);
        } elseif ($run->type === ServerBackupRun::TYPE_FILES) {
            $summary = $files->createFullBackup();
            $result = ['run_path' => (string) ($summary['run_path'] ?? '')];
            $message = 'File snapshot created: '.($result['run_path'] ?: 'unknown');
        } elseif ($run->type === ServerBackupRun::TYPE_INSPECTION) {
            $files->prepareInspectionManifest();
            $result = $request;
            $message = 'File backup is ready to view.';
        } elseif ($run->type === ServerBackupRun::TYPE_FILE_IMPORT) {
            $uploadId = (string) ($request['upload_id'] ?? '');
            $upload = $fileUploads->sessionForImport($uploadId, (string) $run->requested_by);
            $lastProgress = 14;
            $summary = $fileImports->importArchive(
                (string) $upload['archive_path'],
                (string) ($upload['filename'] ?? $request['filename'] ?? 'file-backup.zip'),
                function (int $progress, string $message) use ($run, &$lastProgress): void {
                    $progress = max(15, min(100, $progress));
                    if ($progress <= $lastProgress && $progress < 100) {
                        return;
                    }
                    $run->update([
                        'progress' => $progress,
                        'message' => $message,
                    ]);
                    $lastProgress = $progress;
                }
            );
            $result = [
                'mode' => (string) $summary['mode'],
                'filename' => (string) $summary['filename'],
                'run_path' => (string) $summary['run_path'],
                'uploaded_files' => (int) $summary['uploaded_files'],
                'deleted_files' => (int) $summary['deleted_files'],
                'size' => (int) $summary['size'],
            ];
            $message = 'File backup validated and ready to review.';
        } else {
            [$rootPath, $archiveName] = $this->archiveSource($run->type, $request, $files);
            $result = $this->createArchive($rootPath, $archiveName, (string) $run->id);
            $message = 'Download is ready: '.$result['archive_name'];
        }

        if ($run->type === ServerBackupRun::TYPE_FILE_IMPORT) {
            $fileUploads->markImportCompleted((string) ($request['upload_id'] ?? ''));
        }

        $run->update([
            'status' => ServerBackupRun::STATUS_COMPLETED,
            'progress' => 100,
            'message' => $message,
            'result' => $result,
            'finished_at' => now(),
        ]);
    }

    private function runningMessage(string $type): string
    {
        return match ($type) {
            ServerBackupRun::TYPE_DATABASE => 'Creating database backup…',
            ServerBackupRun::TYPE_FILES => 'Creating full file backup…',
            ServerBackupRun::TYPE_INSPECTION => 'Indexing and comparing backup files…',
            ServerBackupRun::TYPE_FILE_IMPORT => 'Validating uploaded file backup…',
            default => 'Preparing ZIP download…',
        };
    }

    /** @param array<string, mixed> $request @return array{0:string,1:string} */
    private function archiveSource(string $type, array $request, FileBackupService $files): array
    {
        if ($type === ServerBackupRun::TYPE_BACKUP_ARCHIVE) {
            $mode = (string) ($request['mode'] ?? '');
            $filename = basename((string) ($request['filename'] ?? ''));
            $root = Storage::disk('local')->path($files->backupPath($mode, $filename));

            return [$root, 'file-backup-'.$filename.'.zip'];
        }

        return $type === ServerBackupRun::TYPE_MEDIA_ARCHIVE
            ? [Storage::disk('media')->path('/'), 'media-files-'.now()->format('Ymd-His').'.zip']
            : [Storage::disk('local')->path('finance'), 'finance-files-'.now()->format('Ymd-His').'.zip'];
    }

    /** @return array{archive_path:string,archive_name:string} */
    private function createArchive(string $rootPath, string $archiveName, string $runId): array
    {
        if (! is_dir($rootPath)) {
            throw new RuntimeException('Archive source directory does not exist.');
        }

        $directory = storage_path('app/backups/generated');
        if (! is_dir($directory) && ! mkdir($directory, 0775, true) && ! is_dir($directory)) {
            throw new RuntimeException('Unable to create the generated download directory.');
        }

        foreach (glob($directory.'/*.zip') ?: [] as $oldArchive) {
            if (is_file($oldArchive) && filemtime($oldArchive) < now()->subDay()->timestamp) {
                @unlink($oldArchive);
            }
        }

        $finalPath = $directory.'/'.$runId.'.zip';
        $temporaryPath = $directory.'/'.$runId.'.tmp.zip';
        $process = new Process(['zip', '-0', '-qr', $temporaryPath, '.'], $rootPath, null, null, 3600);
        $process->run();
        if (! $process->isSuccessful() || ! is_file($temporaryPath)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Unable to create ZIP archive: '.$process->getErrorOutput());
        }

        rename($temporaryPath, $finalPath);

        return ['archive_path' => $finalPath, 'archive_name' => $archiveName];
    }

    public function failed(Throwable $exception): void
    {
        $run = ServerBackupRun::query()->find($this->runId);
        if ($run instanceof ServerBackupRun && $run->type === ServerBackupRun::TYPE_FILE_IMPORT) {
            app(FileBackupUploadService::class)->cleanupUpload((string) ($run->result['upload_id'] ?? ''));
        }

        ServerBackupRun::query()->whereKey($this->runId)->update([
            'status' => ServerBackupRun::STATUS_FAILED,
            'progress' => 100,
            'message' => 'Backup failed.',
            'error_message' => mb_substr($exception->getMessage(), 0, 5000),
            'finished_at' => now(),
        ]);
    }
}
