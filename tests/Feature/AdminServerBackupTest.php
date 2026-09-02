<?php

namespace Tests\Feature;

use App\Jobs\RunServerBackup;
use App\Models\ServerBackupRun;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\DatabaseBackupService;
use App\Services\FileBackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
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
            ->assertSee('Site Dependencies')
            ->assertSee('ImageMagick')
            ->assertSee('Poppler PDF text')
            ->assertSee('Database dump client')
            ->assertSee('Feature dependency')
            ->assertSee('Media Download Offload')
            ->assertSee('Disabled; Laravel will serve files directly')
            ->assertSee('Backups &amp; Downloads', false)
            ->assertDontSee('Database Backup')
            ->assertDontSee('Bulk File Download');

        $content = $response->getContent();
        self::assertIsString($content);
        self::assertLessThan(strpos($content, 'Site Dependencies'), strpos($content, 'Runtime'));
        self::assertLessThan(strpos($content, 'Deployment'), strpos($content, 'Site Dependencies'));
    }

    public function test_admin_can_queue_database_and_file_backups_without_waiting_for_them(): void
    {
        Queue::fake();
        $admin = $this->createAdminUser();

        $databaseResponse = $this->actingAs($admin)->postJson(route('admin.server.database.backup-now'));
        $databaseResponse
            ->assertAccepted()
            ->assertJsonPath('run.type', ServerBackupRun::TYPE_DATABASE)
            ->assertJsonPath('run.status', ServerBackupRun::STATUS_QUEUED);

        $fileResponse = $this->actingAs($admin)->postJson(route('admin.server.files.backup-now'));
        $fileResponse
            ->assertAccepted()
            ->assertJsonPath('run.type', ServerBackupRun::TYPE_FILES)
            ->assertJsonPath('run.status', ServerBackupRun::STATUS_QUEUED);

        Queue::assertPushed(RunServerBackup::class, 2);
        $this->assertDatabaseCount('server_backup_runs', 2);
    }

    public function test_backup_status_endpoint_reports_completion_after_the_background_job_runs(): void
    {
        $admin = $this->createAdminUser();
        $run = ServerBackupRun::query()->create([
            'type' => ServerBackupRun::TYPE_DATABASE,
            'status' => ServerBackupRun::STATUS_QUEUED,
            'progress' => 5,
            'requested_by' => $admin->id,
        ]);

        $this->mock(DatabaseBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('createBackup')->once()->andReturn('/tmp/website_test.sql.gz');
            $mock->shouldReceive('resolvedKeepCount')->once()->andReturn(10);
            $mock->shouldReceive('pruneOldBackups')->once()->with(10)->andReturn(0);
        });

        (new RunServerBackup((string) $run->id))->handle(
            app(DatabaseBackupService::class),
            app(FileBackupService::class),
        );

        $this->actingAs($admin)
            ->getJson(route('admin.server.backups.status', $run))
            ->assertOk()
            ->assertJsonPath('run.status', ServerBackupRun::STATUS_COMPLETED)
            ->assertJsonPath('run.progress', 100)
            ->assertJsonPath('run.finished', true)
            ->assertJsonPath('run.message', 'Database backup created: website_test.sql.gz');
    }

    public function test_opening_a_backup_queues_manifest_preparation_instead_of_blocking(): void
    {
        Queue::fake();
        $admin = $this->createAdminUser();
        $this->mock(FileBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasFreshInspectionManifest')->once()->andReturnFalse();
        });

        $this->actingAs($admin)
            ->get(route('admin.server.files.show', ['mode' => 'full', 'filename' => '20260819_full']))
            ->assertOk()
            ->assertSeeText('Preparing backup contents');

        Queue::assertPushed(RunServerBackup::class, 1);
        $this->assertDatabaseHas('server_backup_runs', [
            'type' => ServerBackupRun::TYPE_INSPECTION,
            'status' => ServerBackupRun::STATUS_QUEUED,
        ]);
    }

    public function test_bulk_media_download_is_prepared_in_the_background(): void
    {
        Queue::fake();
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->postJson(route('admin.server.media.download-all'))
            ->assertAccepted()
            ->assertJsonPath('run.type', ServerBackupRun::TYPE_MEDIA_ARCHIVE)
            ->assertJsonPath('run.status', ServerBackupRun::STATUS_QUEUED);

        Queue::assertPushed(RunServerBackup::class, 1);
    }

    public function test_completed_generated_archive_can_be_downloaded(): void
    {
        $admin = $this->createAdminUser();
        $directory = storage_path('app/backups/generated');
        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }
        $path = $directory.'/test-download.zip';
        file_put_contents($path, 'zip contents');

        $run = ServerBackupRun::query()->create([
            'type' => ServerBackupRun::TYPE_MEDIA_ARCHIVE,
            'status' => ServerBackupRun::STATUS_COMPLETED,
            'progress' => 100,
            'result' => ['archive_path' => $path, 'archive_name' => 'media-files.zip'],
            'finished_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.server.backups.download', $run))
            ->assertOk()
            ->assertDownload('media-files.zip');

        @unlink($path);
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

        $this->mock(FileBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('resolvedKeepCount')
                ->once()
                ->with('full')
                ->andReturn(12);
            $mock->shouldReceive('resolvedKeepCount')
                ->once()
                ->with('incremental')
                ->andReturn(35);
            $mock->shouldReceive('listBackups')
                ->once()
                ->andReturn([
                    [
                        'filename' => '20260409_011500_full',
                        'mode' => 'full',
                        'created_at' => '2026-04-09T01:15:00+10:00',
                        'uploaded_files' => 12,
                        'deleted_files' => 0,
                        'size' => 2048,
                        'window_hours' => null,
                    ],
                    [
                        'filename' => '20260408_011500_incremental_24h',
                        'mode' => 'incremental',
                        'created_at' => '2026-04-08T01:15:00+10:00',
                        'uploaded_files' => 3,
                        'deleted_files' => 1,
                        'size' => 512,
                        'window_hours' => 24,
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
            ->assertSee('data-sm-file-upload', false)
            ->assertSeeText('paste a document')
            ->assertSee('20260409_011500_full')
            ->assertSee('20260408_011500_incremental_24h')
            ->assertSee('Incremental')
            ->assertSee('fa-rotate-left', false)
            ->assertSee('Bulk File Download');
    }

    public function test_admin_can_view_and_restore_from_a_file_backup_run(): void
    {
        $admin = $this->createAdminUser();

        $this->mock(FileBackupService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('hasFreshInspectionManifest')
                ->once()
                ->andReturnTrue();
            $mock->shouldReceive('inspectBackupRun')
                ->once()
                ->with('full', '20260409_011500_full', 'media')
                ->andReturn([
                    'backup' => [
                        'filename' => '20260409_011500_full',
                        'mode' => 'full',
                        'created_at' => '2026-04-09T01:15:00+10:00',
                        'uploaded_files' => 2,
                        'deleted_files' => 0,
                        'size' => 2048,
                    ],
                    'run_path' => 'backups/files/full/20260409_011500_full',
                    'path_prefix' => 'media',
                    'breadcrumbs' => [
                        ['label' => 'media', 'path' => 'media'],
                    ],
                    'entries' => [
                        [
                            'type' => 'file',
                            'path' => 'media/banner.png',
                            'name' => 'banner.png',
                            'source_label' => 'Media files',
                            'size' => 1024,
                            'last_modified' => now()->timestamp,
                            'status' => 'snapshot',
                            'current_state' => [
                                'state' => 'same',
                                'label' => 'Matches current site',
                                'tone' => 'neutral',
                            ],
                        ],
                    ],
                    'deleted_entries' => [],
                ]);

            $mock->shouldReceive('restoreBackupItems')
                ->once()
                ->with('full', '20260409_011500_full', ['media/banner.png'])
                ->andReturn([
                    'restored_files' => 1,
                    'restored_paths' => ['media/banner.png'],
                    'skipped_paths' => [],
                ]);
        });

        $this->actingAs($admin)
            ->get(route('admin.server.files.show', [
                'mode' => 'full',
                'filename' => '20260409_011500_full',
                'path' => 'media',
            ]))
            ->assertOk()
            ->assertSee('Restore Selected')
            ->assertSee('banner.png')
            ->assertSee('Clear all');

        $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.server.files.restore', [
                'mode' => 'full',
                'filename' => '20260409_011500_full',
                'path' => 'media',
            ]), [
                'selected_items' => ['media/banner.png'],
                'path' => 'media',
            ], ['X-CSRF-TOKEN' => 'test-token'])
            ->assertRedirect(route('admin.server.files.show', [
                'mode' => 'full',
                'filename' => '20260409_011500_full',
                'path' => 'media',
            ]))
            ->assertSessionHas('message', 'Restored 1 file(s).')
            ->assertSessionHas('message-title', 'Restore complete')
            ->assertSessionHas('message-type', 'warning');
    }

    public function test_admin_can_download_selected_items_from_a_file_backup_run(): void
    {
        $admin = $this->createAdminUser();
        $zipPath = tempnam(sys_get_temp_dir(), 'file-backup-test-');
        $this->assertIsString($zipPath);
        file_put_contents($zipPath, 'fake zip data');

        $this->mock(FileBackupService::class, function (MockInterface $mock) use ($zipPath): void {
            $mock->shouldReceive('downloadBackupItems')
                ->once()
                ->with('full', '20260409_011500_full', ['media/banner.png'])
                ->andReturn([
                    'zip_path' => $zipPath,
                    'zip_name' => 'file-backup-20260409_011500_full.zip',
                    'included_files' => 1,
                    'skipped_paths' => [],
                ]);
        });

        $response = $this->actingAs($admin)
            ->withSession(['_token' => 'test-token'])
            ->post(route('admin.server.files.download-selected', [
                'mode' => 'full',
                'filename' => '20260409_011500_full',
            ]), [
                'selected_items' => ['media/banner.png'],
                'path' => 'media',
            ], ['X-CSRF-TOKEN' => 'test-token']);

        $response->assertOk();
        $response->assertDownload('file-backup-20260409_011500_full.zip');
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
