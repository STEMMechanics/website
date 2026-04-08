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
}
