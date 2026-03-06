<?php

namespace Tests\Feature;

use App\Services\MinecraftWebhookBridgeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

class StemcraftPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_stemcraft_pages_render(): void
    {
        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Creative, community-minded Minecraft')
            ->assertSee('Server info');

        $this->get(route('stemcraft.join'))
            ->assertOk()
            ->assertSee('Join with the right details');

        $this->get(route('stemcraft.rules'))
            ->assertOk()
            ->assertSee('The rules are here to protect the community');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSee('The basics, without having to dig through everything else first');

        $this->get(route('stemcraft.leaderboards'))
            ->assertOk()
            ->assertSee('Cached STEMCraft player stats');
    }

    public function test_public_stemcraft_overview_shows_live_server_snapshot_when_available(): void
    {
        $this->mock(MinecraftWebhookBridgeService::class, function (MockInterface $mock): void {
            $mock->shouldReceive('connectionSummary')
                ->once()
                ->andReturn([
                    'configured' => true,
                    'target' => 'https://example.test/webhooks/stemcraft/server',
                ]);
            $mock->shouldReceive('requestStatus')
                ->once()
                ->with(3)
                ->andReturn([
                    'server_name' => 'STEMCraft',
                    'minecraft_version' => '1.21.11',
                    'players' => [
                        'online' => 4,
                        'max' => 20,
                    ],
                    'worlds' => [
                        ['name' => 'world'],
                        ['name' => 'world_nether'],
                        ['name' => 'world_the_end'],
                    ],
                    'tps' => [
                        'one_minute' => 19.95,
                    ],
                    'timestamp' => now()->subMinute()->toIso8601String(),
                ]);
        });

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Players online')
            ->assertSee('4 / 20')
            ->assertSee('Worlds')
            ->assertSee('3')
            ->assertSee('1.21.11')
            ->assertSee('Lobby')
            ->assertDontSee('world_nether')
            ->assertDontSee('world_the_end');
    }
}
