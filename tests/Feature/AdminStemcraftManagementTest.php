<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MinecraftWebhookBridgeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminStemcraftManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_view_management_console(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        $this->mockManagementStatus();

        $response = $this->actingAs($admin)->get(route('admin.stemcraft.management.index'));

        $response->assertOk();
        $response->assertSeeText('Server Management');
        $response->assertSeeText('https://example.test/webhooks/stemcraft/server');
        $response->assertSeeText('STEMCraft');
        $response->assertSeeText('1 / 20');
        $response->assertSeeText('Free memory');
        $response->assertSeeText('512 MB');
        $response->assertSeeText('world');
        $response->assertSeeText('Command History');
        $response->assertSee('data-management-refresh', false);
        $response->assertSee('stemcraft-management-command-form', false);
        $response->assertSee('text-green-700', false);
    }

    public function test_admin_can_fetch_management_status_snapshot(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        $this->mockManagementStatus();

        $response = $this->actingAs($admin)->getJson(route('admin.stemcraft.management.snapshot'));

        $response->assertOk();
        $response->assertJsonStructure(['resultsHtml']);
        $response->assertSeeText('Refresh status');
        $response->assertSeeText('20.00');
        $response->assertSeeText('19.98');
        $response->assertSeeText('19.95');
    }

    public function test_admin_can_execute_management_method(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        $this->mock(MinecraftWebhookBridgeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('requestCommand')
                ->once()
                ->with('list')
                ->andReturn([
                    'command' => 'list',
                    'success' => true,
                    'output' => 'There are 0 of a max of 20 players online',
                    'truncated' => false,
                    'duration_millis' => 12,
                    'timestamp' => '2026-03-04T12:34:56Z',
                ]);
        });

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.management.execute'), [
            'command' => 'list',
        ]);

        $response->assertRedirect(route('admin.stemcraft.management.index'));
        $response->assertSessionHas('minecraft_management.command', 'list');
        $response->assertSessionHas('minecraft_management.command_result.output', 'There are 0 of a max of 20 players online');
        $response->assertSessionMissing('minecraft_management.command_error');
    }

    public function test_admin_can_execute_management_method_as_json(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        $this->mock(MinecraftWebhookBridgeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('requestCommand')
                ->once()
                ->with('list')
                ->andReturn([
                    'command' => 'list',
                    'success' => true,
                    'output' => 'There are 0 of a max of 20 players online',
                    'truncated' => false,
                    'duration_millis' => 12,
                    'timestamp' => '2026-03-04T12:34:56Z',
                ]);
        });

        $response = $this->actingAs($admin)->postJson(route('admin.stemcraft.management.execute'), [
            'command' => 'list',
        ]);

        $response->assertOk();
        $response->assertJson([
            'ok' => true,
            'command' => 'list',
            'result' => [
                'command' => 'list',
                'success' => true,
                'output' => 'There are 0 of a max of 20 players online',
                'truncated' => false,
                'duration_millis' => 12,
                'timestamp' => '2026-03-04T12:34:56Z',
            ],
        ]);
    }

    public function test_non_admin_user_cannot_access_management_console(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.stemcraft.management.index'));

        $response->assertForbidden();
    }

    private function mockManagementStatus(): void
    {
        $connection = [
            'configured' => true,
            'target' => 'https://example.test/webhooks/stemcraft/server',
        ];

        $this->mock(MinecraftWebhookBridgeService::class, function (MockInterface $mock) use ($connection): void {
            $mock->shouldReceive('connectionSummary')
                ->once()
                ->andReturn($connection);
            $mock->shouldReceive('requestStatus')
                ->once()
                ->andReturn([
                    'server_name' => 'STEMCraft',
                    'bukkit_name' => 'Paper',
                    'bukkit_version' => 'git-Paper-1.21.11',
                    'minecraft_version' => '1.21.11',
                    'plugin_version' => '2.4.0',
                    'online_mode' => true,
                    'players' => [
                        'online' => 1,
                        'max' => 20,
                    ],
                    'memory' => [
                        'free_bytes' => 512 * 1024 * 1024,
                        'used_bytes' => 256 * 1024 * 1024,
                        'allocated_bytes' => 1024 * 1024 * 1024,
                        'max_bytes' => 2 * 1024 * 1024 * 1024,
                    ],
                    'loaded_chunks' => 42,
                    'worlds' => [
                        [
                            'name' => 'world',
                            'players' => 1,
                            'loaded_chunks' => 42,
                        ],
                    ],
                    'tps' => [
                        'one_minute' => 20.0,
                        'five_minute' => 19.98,
                        'fifteen_minute' => 19.95,
                    ],
                    'timestamp' => '2026-03-04T12:34:56Z',
                ]);
        });
    }
}
