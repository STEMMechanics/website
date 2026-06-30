<?php

namespace Tests\Unit;

use App\Services\FileBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FileBackupServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_full_file_backup_and_prunes_old_full_runs(): void
    {
        Storage::fake('media');
        Storage::fake('local');

        Storage::disk('media')->put('gallery/photo.jpg', 'image-data');
        Storage::disk('local')->put('finance/private/report.pdf', 'finance-data');

        $oldRunPath = 'backups/files/full/20240101_000000_full';
        Storage::disk('local')->makeDirectory($oldRunPath.'/files/media/gallery');
        Storage::disk('local')->put($oldRunPath.'/files/media/gallery/old.jpg', 'old-data');
        Storage::disk('local')->put($oldRunPath.'/manifest.json', '{}');

        $service = $this->app->make(FileBackupService::class);
        $summary = $service->createFullBackup(1);

        $this->assertSame('full', $summary['mode']);
        $this->assertSame(2, $summary['files']['uploaded_files']);
        $this->assertSame(1, $summary['pruned']);
        Storage::disk('local')->assertExists($summary['run_path'].'/files/media/gallery/photo.jpg');
        Storage::disk('local')->assertExists($summary['run_path'].'/files/finance/private/report.pdf');
        $this->assertFalse(Storage::disk('local')->exists($oldRunPath));
    }

    public function test_it_creates_incremental_file_backup_for_recent_files_and_records_deletions(): void
    {
        Storage::fake('media');
        Storage::fake('local');

        Storage::disk('media')->put('gallery/old.jpg', 'old-data');
        Storage::disk('media')->put('gallery/recent.jpg', 'recent-data');
        Storage::disk('local')->put('finance/private/delete-me.pdf', 'delete-me');

        touch(Storage::disk('media')->path('gallery/old.jpg'), now()->subDays(3)->timestamp);
        touch(Storage::disk('local')->path('finance/private/delete-me.pdf'), now()->subHours(2)->timestamp);

        $service = $this->app->make(FileBackupService::class);
        $service->createFullBackup(5);

        Storage::disk('media')->put('gallery/recent.jpg', 'recent-data-updated');
        Storage::disk('media')->put('gallery/new.jpg', 'new-data');
        Storage::disk('local')->delete('finance/private/delete-me.pdf');

        $summary = $service->createIncrementalBackup('1d', 5);

        $this->assertSame('incremental', $summary['mode']);
        $this->assertSame(2, $summary['files']['uploaded_files']);
        $this->assertSame(1, $summary['files']['deleted_files']);
        $this->assertSame(['media/gallery/new.jpg', 'media/gallery/recent.jpg'], $summary['files']['uploaded_paths']);
        $this->assertSame(['private/delete-me.pdf'], $summary['files']['deleted_paths']['finance']);
        Storage::disk('local')->assertExists($summary['run_path'].'/files/media/gallery/recent.jpg');
        Storage::disk('local')->assertExists($summary['run_path'].'/files/media/gallery/new.jpg');
        Storage::disk('local')->assertMissing($summary['run_path'].'/files/media/gallery/old.jpg');
    }

    public function test_it_lists_readable_backups_when_one_run_directory_is_not_readable(): void
    {
        Storage::fake('local');

        $readableRunPath = 'backups/files/full/20260630_010000_full';
        $unreadableRunPath = 'backups/files/incremental/20260630_011524_incremental_24h';

        Storage::disk('local')->put($readableRunPath.'/files/media/photo.jpg', 'image-data');
        Storage::disk('local')->put($readableRunPath.'/manifest.json', json_encode([
            'mode' => 'full',
            'created_at' => '2026-06-30T01:00:00+10:00',
            'uploaded_files' => 1,
            'deleted_files' => 0,
        ], JSON_THROW_ON_ERROR));

        Storage::disk('local')->makeDirectory($unreadableRunPath);
        $unreadableAbsolutePath = Storage::disk('local')->path($unreadableRunPath);
        chmod($unreadableAbsolutePath, 0000);

        try {
            $backups = $this->app->make(FileBackupService::class)->listBackups();
        } finally {
            chmod($unreadableAbsolutePath, 0775);
        }

        $backupsByName = collect($backups)->keyBy('filename');

        $this->assertCount(2, $backups);
        $this->assertTrue($backupsByName['20260630_010000_full']['is_readable']);
        $this->assertFalse($backupsByName['20260630_011524_incremental_24h']['is_readable']);
        $this->assertNotEmpty($backupsByName['20260630_011524_incremental_24h']['error']);
    }
}
