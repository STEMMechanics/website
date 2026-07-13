<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StemcraftPunishmentsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_removed_public_punishments_url_redirects_to_stemcraft(): void
    {
        $this->get(route('stemcraft.punishments'))
            ->assertRedirect(route('stemcraft.index'));

        $this->get(route('stemcraft.punishments', ['search' => 'PlayerOne', 'type' => 'ban']))
            ->assertRedirect(route('stemcraft.index'));
    }
}
