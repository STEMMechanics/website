<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\DatabaseBackupService;
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
            ->assertSee('backup:remote')
            ->assertSee('backup.database.keep')
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
