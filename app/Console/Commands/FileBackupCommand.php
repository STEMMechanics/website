<?php

namespace App\Console\Commands;

use App\Services\FileBackupService;
use Illuminate\Console\Command;

class FileBackupCommand extends Command
{
    protected $signature = 'files:backup
        {--full : Create a full file backup. This is the default when no mode is specified.}
        {--incremental : Create an incremental file backup.}
        {--window=24h : Incremental lookback window, such as 24h, 3d, or 1w.}
        {--keep= : Number of backup runs to retain for the selected mode. Defaults to the mode-specific site option.}';

    protected $description = 'Create a local snapshot of media and finance files and prune old snapshots.';

    public function handle(FileBackupService $fileBackupService): int
    {
        try {
            $full = (bool) $this->option('full');
            $incremental = (bool) $this->option('incremental');

            if ($full && $incremental) {
                $this->error('Select either --full or --incremental, not both.');

                return self::FAILURE;
            }

            if ($incremental) {
                $summary = $fileBackupService->createIncrementalBackup(
                    $this->option('window'),
                    $this->option('keep')
                );
            } else {
                $summary = $fileBackupService->createFullBackup($this->option('keep'));
            }

            $this->info('File backup created: '.$summary['run_path']);
            $this->line('Mode: '.ucfirst((string) $summary['mode']));
            if ((string) $summary['mode'] === FileBackupService::MODE_INCREMENTAL) {
                $this->line('Window: '.number_format((int) ($summary['window_hours'] ?? 24)).' hours');
            }
            $this->line('Files uploaded: '.number_format((int) $summary['files']['uploaded_files']));
            $this->line('Files deleted in manifest: '.number_format((int) $summary['files']['deleted_files']));
            $this->line('Pruned old snapshots: '.number_format((int) ($summary['pruned'] ?? 0)));

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('File backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
