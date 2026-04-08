<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\DatabaseBackupService;
use App\Services\FileBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminServerBackupTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_backup_command_uses_resolved_keep_count_when_keep_is_omitted(): void
    {
        $this->mock(DatabaseBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolvedKeepCount')
                ->once()
                ->with(null)
                ->andReturn(240);
            $mock->shouldReceive('createBackup')
                ->once()
                ->andReturn('/tmp/test-backup.sql.gz');
            $mock->shouldReceive('pruneOldBackups')
                ->once()
                ->with(240)
                ->andReturn(0);
        });

        $this->artisan('database:backup')
            ->expectsOutput('Database backup created: /tmp/test-backup.sql.gz')
            ->assertSuccessful();
    }

    public function test_file_backup_command_uses_resolved_keep_count_when_keep_is_omitted(): void
    {
        $this->mock(FileBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createFullBackup')
                ->once()
                ->with(null)
                ->andReturn([
                    'mode' => 'full',
                    'run_path' => 'backups/files/20260409_011500_abcd12',
                    'files' => [
                        'mode' => 'full',
                        'uploaded_files' => 12,
                        'deleted_files' => 0,
                    ],
                    'pruned' => 1,
                ]);
        });

        $this->artisan('files:backup')
            ->expectsOutput('File backup created: backups/files/20260409_011500_abcd12')
            ->expectsOutput('Mode: Full')
            ->expectsOutput('Files uploaded: 12')
            ->expectsOutput('Files deleted in manifest: 0')
            ->expectsOutput('Pruned old snapshots: 1')
            ->assertSuccessful();
    }

    public function test_file_backup_command_supports_incremental_mode_and_custom_window(): void
    {
        $this->mock(FileBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createIncrementalBackup')
                ->once()
                ->with('3d', '8')
                ->andReturn([
                    'mode' => 'incremental',
                    'window_hours' => 72,
                    'run_path' => 'backups/files/incremental/20260409_011500_72h',
                    'files' => [
                        'mode' => 'incremental',
                        'uploaded_files' => 4,
                        'deleted_files' => 1,
                    ],
                    'pruned' => 0,
                ]);
        });

        $this->artisan('files:backup --incremental --window=3d --keep=8')
            ->expectsOutput('File backup created: backups/files/incremental/20260409_011500_72h')
            ->expectsOutput('Mode: Incremental')
            ->expectsOutput('Window: 72 hours')
            ->expectsOutput('Files uploaded: 4')
            ->expectsOutput('Files deleted in manifest: 1')
            ->expectsOutput('Pruned old snapshots: 0')
            ->assertSuccessful();
    }

    public function test_admin_server_page_shows_runtime_versions_and_links_to_backups_page(): void
    {
        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->get(route('admin.server.index'));

        $response
            ->assertOk()
            ->assertSee('Node Version')
            ->assertSee('npm Version')
            ->assertSee('Composer Version')
            ->assertSee('Backups &amp; Downloads', false)
            ->assertDontSee('Database Backup')
            ->assertDontSee('Bulk File Download');

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertLessThan(strpos($content, 'Deployment'), strpos($content, 'Runtime'));
    }

    public function test_admin_server_backups_page_shows_backup_rollback_action(): void
    {
        $admin = $this->createAdminUser();

        $this->mock(DatabaseBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolvedKeepCount')
                ->once()
                ->withNoArgs()
                ->andReturn(240);
            $mock->shouldReceive('listBackups')
                ->once()
                ->andReturn([
                    [
                        'filename' => 'website_20260321_140000.sql.gz',
                        'size' => 2048,
                        'modified_at' => '2026-03-21 14:00:00',
                    ],
                ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.server.backups'))
            ->assertOk()
            ->assertSee('database:backup')
            ->assertSee('backup.database.keep')
            ->assertSee('files:backup')
            ->assertSee('files:backup --full')
            ->assertSee('files:backup --incremental --window=24h')
            ->assertSee('backup.files.full.keep')
            ->assertSee('backup.files.incremental.keep')
            ->assertSee('Full Backup Now')
            ->assertSee('fa-rotate-left', false)
            ->assertSee('Bulk File Download');
    }

    public function test_admin_can_restore_database_from_a_saved_backup_file(): void
    {
        $admin = $this->createAdminUser();
        $filename = 'website_20260321_140000.sql.gz';
        $path = storage_path('framework/testing/'.$filename);

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        file_put_contents($path, 'test backup');

        $this->mock(DatabaseBackupService::class, function (MockInterface $mock) use ($filename, $path): void {
            $mock->shouldReceive('backupPath')
                ->once()
                ->with($filename)
                ->andReturn($path);
            $mock->shouldReceive('restoreBackup')
                ->once()
                ->with($path);
        });

        $this->actingAs($admin)
            ->post(route('admin.server.database.restore', ['filename' => $filename]))
            ->assertRedirect(route('admin.server.backups'))
            ->assertSessionHas('message', 'Database restored from backup: '.$filename)
            ->assertSessionHas('message-title', 'Rollback complete')
            ->assertSessionHas('message-type', 'warning');

        @unlink($path);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create();

        UserGroup::query()->create([
            'user_id' => $user->id,
            'slug' => 'admin',
        ]);

        return $user;
    }
}
