<?php

namespace App\Console\Commands;

use App\Services\DatabaseBackupService;
use Illuminate\Console\Command;

class DatabaseBackupCommand extends Command
{
    protected $signature = 'database:backup {--keep= : Number of backup files to retain. Defaults to site option backup.database.keep}';

    protected $description = 'Create a full compressed database backup and prune old backup files.';

    public function handle(DatabaseBackupService $backupService): int
    {
        $keep = $backupService->resolvedKeepCount($this->option('keep'));

        try {
            $path = $backupService->createBackup();
            $removed = $backupService->pruneOldBackups($keep);

            $this->info('Database backup created: '.$path);
            if ($removed > 0) {
                $this->info('Pruned '.$removed.' old backup file(s).');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Database backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
