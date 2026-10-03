<?php

namespace Tests\Unit;

use App\Models\SiteOption;
use App\Services\DatabaseBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_uses_site_option_keep_count_when_no_override_is_provided(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => DatabaseBackupService::KEEP_COUNT_OPTION],
            ['value' => '240']
        );

        $service = new DatabaseBackupService();

        $this->assertSame(240, $service->resolvedKeepCount());
        $this->assertSame(240, $service->resolvedKeepCount(null));
        $this->assertSame(240, $service->resolvedKeepCount(''));
    }

    public function test_it_prefers_an_explicit_keep_override(): void
    {
        SiteOption::query()->updateOrCreate(
            ['name' => DatabaseBackupService::KEEP_COUNT_OPTION],
            ['value' => '240']
        );

        $service = new DatabaseBackupService();

        $this->assertSame(12, $service->resolvedKeepCount(12));
        $this->assertSame(18, $service->resolvedKeepCount('18'));
    }

    public function test_it_removes_stale_temporary_files_when_listing_backups(): void
    {
        $service = new DatabaseBackupService();
        $filename = 'test_'.bin2hex(random_bytes(4)).'.sql.gz';
        $temporaryPath = $service->backupPath('.'.$filename.'.sql.tmp');

        if (! is_dir(dirname($temporaryPath))) {
            mkdir(dirname($temporaryPath), 0775, true);
        }

        file_put_contents($temporaryPath, 'partial dump');
        touch($temporaryPath, now()->subDay()->subMinute()->getTimestamp());

        $service->listBackups();

        $this->assertFileDoesNotExist($temporaryPath);
    }
}
