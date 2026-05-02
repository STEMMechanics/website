<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CairnsMinecraftPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_cairns_minecraft_page_renders_archive_content(): void
    {
        config()->set('services.cairns_minecraft.creative_complete_url', 'https://cdn.example.com/cairns-minecraft/2205-cm-creative-complete.zip');
        config()->set('services.cairns_minecraft.creative_url', 'https://cdn.example.com/cairns-minecraft/2205-cm-creative.zip');
        config()->set('services.cairns_minecraft.creative_complete_magnet_url', 'magnet:?xt=urn:btih:creative-complete');
        config()->set('services.cairns_minecraft.creative_magnet_url', 'magnet:?xt=urn:btih:creative');
        config()->set('services.cairns_minecraft.survival_magnet_url', 'magnet:?xt=urn:btih:survival');

        $response = $this->get(route('cairns.minecraft'));

        $response->assertOk();
        $response->assertSeeText('Cairns Minecraft');
        $response->assertSeeText('Cairns Minecraft closed in May 2022.');
        $response->assertSeeText('Cairns Minecraft was developed to stimulate ideas and discussion with young people about urban design; architecture; public art and space.');
        $response->assertSeeText('STEMCraft started in July 2023, and many players moved across to the new server.');
        $response->assertSeeText('Workshops, builds, and community activity continued there, giving the same group a place to keep learning, creating, and connecting after Cairns Minecraft closed.');
        $response->assertSeeText('2205-cm-creative-complete.zip');
        $response->assertSeeText('2205-cm-creative.zip');
        $response->assertSeeText('Magnet');
        $response->assertSee('meta name="robots" content="noindex, nofollow"', false);
    }
}
