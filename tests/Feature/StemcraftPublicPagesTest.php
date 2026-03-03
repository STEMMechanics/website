<?php

namespace Tests\Feature;

use Tests\TestCase;

class StemcraftPublicPagesTest extends TestCase
{
    public function test_public_stemcraft_pages_render(): void
    {
        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Creative, community-minded Minecraft');

        $this->get(route('stemcraft.join'))
            ->assertOk()
            ->assertSee('Join STEMCraft');

        $this->get(route('stemcraft.rules'))
            ->assertOk()
            ->assertSee('STEMCraft Rules');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSee('STEMCraft FAQs');
    }
}
