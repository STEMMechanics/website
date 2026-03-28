<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\ServerMaintenanceService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminSiteOptionMaintenanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_run_site_maintenance_refresh(): void
    {
        $admin = $this->createAdminUser();

        $this->mock(ServerMaintenanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshCachesAndRestartQueue')
                ->once()
                ->andReturn([
                    'success' => true,
                    'message' => 'Application caches were cleared and the queue restart was requested.',
                    'commands' => [
                        [
                            'command' => 'optimize:clear',
                            'success' => true,
                            'exit_code' => 0,
                            'output' => 'Cleared cached bootstrap files.',
                            'error' => null,
                        ],
                        [
                            'command' => 'config:clear',
                            'success' => true,
                            'exit_code' => 0,
                            'output' => 'Configuration cache cleared!',
                            'error' => null,
                        ],
                        [
                            'command' => 'cache:clear',
                            'success' => true,
                            'exit_code' => 0,
                            'output' => 'Application cache cleared!',
                            'error' => null,
                        ],
                        [
                            'command' => 'view:clear',
                            'success' => true,
                            'exit_code' => 0,
                            'output' => 'Compiled views cleared!',
                            'error' => null,
                        ],
                        [
                            'command' => 'queue:restart',
                            'success' => true,
                            'exit_code' => 0,
                            'output' => 'Broadcasting queue restart signal.',
                            'error' => null,
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($admin)->postJson(route('admin.site_option.maintenance-refresh'));

        $response->assertOk();
        $response->assertJson([
            'success' => true,
            'message' => 'Application caches were cleared and the queue restart was requested.',
        ]);
        $response->assertJsonCount(5, 'commands');
        $response->assertJsonPath('commands.0.command', 'optimize:clear');
        $response->assertJsonPath('commands.4.command', 'queue:restart');
    }

    public function test_admin_sees_failure_when_site_maintenance_refresh_fails(): void
    {
        $admin = $this->createAdminUser();

        $this->mock(ServerMaintenanceService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('refreshCachesAndRestartQueue')
                ->once()
                ->andReturn([
                    'success' => false,
                    'message' => 'One or more maintenance commands failed.',
                    'commands' => [
                        [
                            'command' => 'optimize:clear',
                            'success' => false,
                            'exit_code' => 1,
                            'output' => '',
                            'error' => 'Unable to write cache files.',
                        ],
                    ],
                ]);
        });

        $response = $this->actingAs($admin)->postJson(route('admin.site_option.maintenance-refresh'));

        $response->assertStatus(500);
        $response->assertJson([
            'success' => false,
            'message' => 'One or more maintenance commands failed.',
        ]);
        $response->assertJsonPath('commands.0.error', 'Unable to write cache files.');
    }

    public function test_non_admin_cannot_run_site_maintenance_refresh(): void
    {
        $user = User::factory()->create();

        $this->mock(ServerMaintenanceService::class, function (MockInterface $mock): void {
            $mock->shouldNotReceive('refreshCachesAndRestartQueue');
        });

        $this->actingAs($user)
            ->postJson(route('admin.site_option.maintenance-refresh'))
            ->assertForbidden();
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'admin']);

        return $user;
    }
}
