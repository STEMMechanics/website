<?php

namespace Tests\Feature;

use App\Models\SiteOption;
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

    public function test_public_stemcraft_overview_groups_bridge_worlds_without_hiding_other_worlds(): void
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
                        'online' => 7,
                        'max' => 20,
                    ],
                    'worlds' => [
                        ['name' => 'world'],
                        ['name' => 'bridge_forest'],
                        ['name' => 'bridge_desert'],
                        ['name' => 'bridge_cavern'],
                        ['name' => 'bridge_ruins'],
                        ['name' => 'bridge_snow'],
                        ['name' => 'overworld'],
                        ['name' => 'nether'],
                        ['name' => 'the_end'],
                        ['name' => 'world_nether'],
                        ['name' => 'world_the_end'],
                    ],
                    'tps' => [
                        'one_minute' => 20.0,
                    ],
                    'timestamp' => now()->subMinute()->toIso8601String(),
                ]);
        });

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Lobby')
            ->assertSee('Overworld')
            ->assertSee('Nether')
            ->assertSee('The End')
            ->assertSee('Bridge (5 arenas)')
            ->assertSee('Cavern')
            ->assertSee('Desert')
            ->assertSee('Forest')
            ->assertSee('Ruins')
            ->assertSee('Snow')
            ->assertDontSee('Bridge Forest')
            ->assertDontSee('Bridge Desert')
            ->assertDontSee('world_nether')
            ->assertDontSee('world_the_end');
    }

    public function test_public_stemcraft_overview_groups_other_prefixed_world_families(): void
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
                        'online' => 9,
                        'max' => 20,
                    ],
                    'worlds' => [
                        ['name' => 'world'],
                        ['name' => 'parkour_easy'],
                        ['name' => 'parkour_hard'],
                        ['name' => 'bedwars_desert'],
                        ['name' => 'bedwars_forest'],
                        ['name' => 'survival'],
                        ['name' => 'survival_nether'],
                        ['name' => 'survival_the_end'],
                    ],
                    'tps' => [
                        'one_minute' => 19.8,
                    ],
                    'timestamp' => now()->subMinute()->toIso8601String(),
                ]);
        });

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Lobby')
            ->assertSee('Survival (3 worlds)')
            ->assertSee('Overworld')
            ->assertSee('Nether')
            ->assertSee('The End')
            ->assertSee('Parkour (2 arenas)')
            ->assertSee('Easy')
            ->assertSee('Hard')
            ->assertSee('Bedwars (2 arenas)')
            ->assertSee('Desert')
            ->assertSee('Forest')
            ->assertDontSee('Survival Nether')
            ->assertDontSee('Survival The End')
            ->assertDontSee('Parkour Easy')
            ->assertDontSee('Bedwars Forest');
    }

    public function test_public_stemcraft_overview_groups_unconfigured_future_world_families(): void
    {
        SiteOption::query()->create([
            'name' => 'minecraft.public-status-arena-groups',
            'value' => 'bridge, parkour, bedwars, mob_arena',
        ]);

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
                        'online' => 11,
                        'max' => 20,
                    ],
                    'worlds' => [
                        ['name' => 'world'],
                        ['name' => 'creative'],
                        ['name' => 'creative_nether'],
                        ['name' => 'creative_the_end'],
                        ['name' => 'mob_arena_alpha'],
                        ['name' => 'mob_arena_beta'],
                    ],
                    'tps' => [
                        'one_minute' => 19.9,
                    ],
                    'timestamp' => now()->subMinute()->toIso8601String(),
                ]);
        });

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Creative (3 worlds)')
            ->assertSee('Overworld')
            ->assertSee('Nether')
            ->assertSee('The End')
            ->assertSee('Mob Arena (2 arenas)')
            ->assertSee('Alpha')
            ->assertSee('Beta')
            ->assertDontSee('Creative Nether')
            ->assertDontSee('Mob Arena Alpha');
    }
}
