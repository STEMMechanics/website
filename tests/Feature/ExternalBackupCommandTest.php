<?php

namespace Tests\Feature;

use App\Services\ExternalBackupService;
use Mockery\MockInterface;
use Tests\TestCase;

class ExternalBackupCommandTest extends TestCase
{
    public function test_remote_backup_command_reports_summary_from_service(): void
    {
        $this->mock(ExternalBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('run')
                ->once()
                ->with([
                    'disk' => null,
                    'path' => null,
                    'database' => null,
                    'files' => null,
                    'files_mode' => null,
                ])
                ->andReturn([
                    'disk' => 'backup_remote',
                    'run_path' => 'offsite-backups/20260321_160000_abcd12',
                    'database' => [
                        'remote_path' => 'offsite-backups/20260321_160000_abcd12/database/remote_20260321.sql.gz',
                    ],
                    'files' => [
                        'mode' => 'incremental',
                        'uploaded_files' => 4,
                        'deleted_files' => 1,
                    ],
                ]);
        });

        $this->artisan('backup:remote')
            ->expectsOutput('Remote backup completed.')
            ->expectsOutput('Target disk: backup_remote')
            ->expectsOutput('Run path: offsite-backups/20260321_160000_abcd12')
            ->expectsOutput('Database: offsite-backups/20260321_160000_abcd12/database/remote_20260321.sql.gz')
            ->expectsOutput('Files mode: incremental')
            ->expectsOutput('Files uploaded: 4')
            ->expectsOutput('Files deleted in manifest: 1')
            ->assertSuccessful();
    }
}
