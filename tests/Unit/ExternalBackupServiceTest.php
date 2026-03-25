<?php

namespace Tests\Unit;

use App\Models\SiteOption;
use App\Services\DatabaseBackupService;
use App\Services\ExternalBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Tests\TestCase;

class ExternalBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_can_write_a_full_offsite_backup_for_database_and_files(): void
    {
        config()->set('filesystems.disks.remote', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/remote'),
        ]);

        Storage::fake('media');
        Storage::fake('local');
        Storage::fake('remote');

        Storage::disk('media')->put('gallery/photo.jpg', 'image-data');
        Storage::disk('local')->put('finance/report.pdf', 'finance-data');

        $databasePath = $this->makeDatabaseBackupFixture('full-backup.sql.gz', 'database-backup');

        $this->mock(DatabaseBackupService::class, function (MockInterface $mock) use ($databasePath): void {
            $mock->shouldReceive('createBackup')
                ->once()
                ->with('remote')
                ->andReturn($databasePath);
        });

        $this->configureRemoteBackupOptions('full');

        $service = $this->app->make(ExternalBackupService::class);
        $summary = $service->run();

        $this->assertSame('remote', $summary['disk']);
        $this->assertSame('offsite-backups', $summary['path']);
        $this->assertSame('full', $summary['files']['mode']);
        $this->assertSame(2, $summary['files']['uploaded_files']);

        Storage::disk('remote')->assertExists($summary['database']['remote_path']);
        Storage::disk('remote')->assertExists($summary['run_path'].'/files/media/gallery/photo.jpg');
        Storage::disk('remote')->assertExists($summary['run_path'].'/files/finance/report.pdf');
        Storage::disk('remote')->assertExists($summary['run_path'].'/backup-manifest.json');
        Storage::disk('remote')->assertExists('offsite-backups/state/files-manifest.json');
    }

    public function test_incremental_offsite_backup_only_uploads_changed_files_and_records_deletions(): void
    {
        config()->set('filesystems.disks.remote', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/remote'),
        ]);

        Storage::fake('media');
        Storage::fake('local');
        Storage::fake('remote');

        Storage::disk('media')->put('gallery/changed.jpg', 'old-image');
        Storage::disk('media')->put('gallery/unchanged.jpg', 'same-image');
        Storage::disk('local')->put('finance/old-report.pdf', 'old-report');

        $this->configureRemoteBackupOptions('full');

        $initialDatabasePath = $this->makeDatabaseBackupFixture('initial-backup.sql.gz', 'initial-db');
        $incrementalDatabasePath = $this->makeDatabaseBackupFixture('incremental-backup.sql.gz', 'incremental-db');

        $this->mock(DatabaseBackupService::class, function (MockInterface $mock) use ($initialDatabasePath, $incrementalDatabasePath): void {
            $mock->shouldReceive('createBackup')
                ->once()
                ->with('remote')
                ->andReturn($initialDatabasePath);
            $mock->shouldReceive('createBackup')
                ->once()
                ->with('remote')
                ->andReturn($incrementalDatabasePath);
        });

        $service = $this->app->make(ExternalBackupService::class);
        $service->run();

        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_FILES_MODE],
            ['value' => 'incremental']
        );

        Storage::disk('media')->put('gallery/changed.jpg', 'new-image-data');
        Storage::disk('media')->put('gallery/new.jpg', 'brand-new');
        Storage::disk('local')->delete('finance/old-report.pdf');

        $summary = $service->run();

        $this->assertSame('incremental', $summary['files']['mode']);
        $this->assertSame(2, $summary['files']['uploaded_files']);
        $this->assertSame(1, $summary['files']['deleted_files']);
        $this->assertSame(['gallery/changed.jpg', 'gallery/new.jpg'], $summary['files']['sources']['media']['uploaded_paths']);
        $this->assertSame(['old-report.pdf'], $summary['files']['sources']['finance']['deleted_paths']);

        Storage::disk('remote')->assertExists($summary['run_path'].'/files/media/gallery/changed.jpg');
        Storage::disk('remote')->assertExists($summary['run_path'].'/files/media/gallery/new.jpg');
        Storage::disk('remote')->assertMissing($summary['run_path'].'/files/media/gallery/unchanged.jpg');
        Storage::disk('remote')->assertExists($summary['run_path'].'/backup-manifest.json');
    }

    private function configureRemoteBackupOptions(string $filesMode): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_TARGET_DISK],
            ['value' => 'remote']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_TARGET_PATH],
            ['value' => 'offsite-backups']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_INCLUDE_DATABASE],
            ['value' => '1']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_INCLUDE_FILES],
            ['value' => '1']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_FILES_MODE],
            ['value' => $filesMode]
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ExternalBackupService::OPTION_FILE_SOURCES],
            ['value' => json_encode(ExternalBackupService::defaultFileSources(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)]
        );
    }

    private function makeDatabaseBackupFixture(string $filename, string $contents): string
    {
        $path = storage_path('framework/testing/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }

        file_put_contents($path, $contents);

        return $path;
    }
}
