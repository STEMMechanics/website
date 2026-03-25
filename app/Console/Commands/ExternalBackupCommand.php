<?php

namespace App\Console\Commands;

use App\Services\ExternalBackupService;
use Illuminate\Console\Command;

class ExternalBackupCommand extends Command
{
    protected $signature = 'backup:remote
        {--disk= : Target Laravel filesystem disk for the offsite backup}
        {--path= : Base path on the target disk}
        {--database= : Include the database backup (1 or 0)}
        {--files= : Include configured file sources (1 or 0)}
        {--files-mode= : File backup mode: full or incremental}';

    protected $description = 'Create an offsite backup to a configured filesystem disk, with full database backups and full or incremental file copies.';

    public function handle(ExternalBackupService $externalBackupService): int
    {
        try {
            $summary = $externalBackupService->run([
                'disk' => $this->option('disk'),
                'path' => $this->option('path'),
                'database' => $this->option('database'),
                'files' => $this->option('files'),
                'files_mode' => $this->option('files-mode'),
            ]);

            $this->info('Remote backup completed.');
            $this->line('Target disk: '.$summary['disk']);
            $this->line('Run path: '.$summary['run_path']);

            if (is_array($summary['database'] ?? null)) {
                $this->line('Database: '.$summary['database']['remote_path']);
            } else {
                $this->line('Database: skipped');
            }

            if (is_array($summary['files'] ?? null)) {
                $this->line('Files mode: '.$summary['files']['mode']);
                $this->line('Files uploaded: '.number_format((int) $summary['files']['uploaded_files']));
                $this->line('Files deleted in manifest: '.number_format((int) $summary['files']['deleted_files']));
            } else {
                $this->line('Files: skipped');
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Remote backup failed: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
