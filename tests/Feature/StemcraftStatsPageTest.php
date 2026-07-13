<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StemcraftStatsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_leaderboard_urls_redirect_to_stemcraft(): void
    {
        $this->get(route('stemcraft.leaderboards'))
            ->assertRedirect(route('stemcraft.index'));

        $this->get(route('stemcraft.leaderboards', ['player' => 'PlayerOne', 'period' => 'month']))
            ->assertRedirect(route('stemcraft.index'));
    }
}
