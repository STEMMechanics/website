<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\MinecraftRconService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class AdminStemcraftRconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_execute_rcon_command(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        $this->mock(MinecraftRconService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('execute')
                ->once()
                ->with('list')
                ->andReturn('There are 0 of a max of 20 players online');
        });

        $response = $this->actingAs($admin)->post(route('admin.stemcraft.rcon.execute'), [
            'command' => 'list',
        ]);

        $response->assertRedirect(route('admin.stemcraft.rcon.index'));
        $response->assertSessionHas('minecraft_rcon.command', 'list');
        $response->assertSessionHas('minecraft_rcon.output', 'There are 0 of a max of 20 players online');
        $response->assertSessionMissing('minecraft_rcon.error');
    }

    public function test_non_admin_user_cannot_access_rcon_console(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('admin.stemcraft.rcon.index'));

        $response->assertForbidden();
    }
}
