<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StemcraftPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_stemcraft_pages_render(): void
    {
        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('STEMCraft is a Minecraft server for STEMMechanics kids, families, schools, and OSHC groups.')
            ->assertSee('Who can access STEMCraft')
            ->assertSee('Safety and environment')
            ->assertSee('Technical details');

        $this->get(route('stemcraft.join'))
            ->assertOk()
            ->assertSee('Access must be set up first, then joining is straightforward.');

        $this->get(route('stemcraft.rules'))
            ->assertOk()
            ->assertSee('These rules help STEMCraft stay safe, calm, and easy to use for families and groups.');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSee('Clear answers for parents, schools, and workshop families.');

        $this->get(route('stemcraft.leaderboards'))
            ->assertOk()
            ->assertSee('Cached STEMCraft player stats');
    }
}
