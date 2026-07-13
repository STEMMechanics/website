<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StemcraftPublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_stemcraft_pages_render(): void
    {
        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Continue Building Beyond the Workshop')
            ->assertSee('STEMCraft Server')
            ->assertSee('STEMCraft is the online world of STEMMechanics')
            ->assertSee('Community expectations');

        $this->get(route('stemcraft.join'))
            ->assertOk()
            ->assertSee('Get Ready to Build Online')
            ->assertSee('Connection details')
            ->assertSee('STEMCraft Server');

        $this->get(route('stemcraft.rules'))
            ->assertOk()
            ->assertSee('Be kind and respectful')
            ->assertSee('Protect personal information');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSee('What is STEMCraft?')
            ->assertSee('How is participant safety managed?');

        $this->get(route('stemcraft.leaderboards'))
            ->assertRedirect(route('stemcraft.index'));

        $this->get(route('stemcraft.punishments'))
            ->assertRedirect(route('stemcraft.index'));

        $this->get('/account/stemcraft')
            ->assertRedirect(route('stemcraft.join'));
    }

    public function test_stemcraft_landing_uses_editable_challenge_and_community_build_options(): void
    {
        $this->setSiteOption('stemcraft.monthly-challenge.title', 'Build a Solar Workshop');
        $this->setSiteOption('stemcraft.monthly-challenge.description', 'Create a workshop that uses daylight and clever storage.');
        $this->setSiteOption('stemcraft.monthly-challenge.prompt', 'Include at least one moving part.');
        $this->setSiteOption('stemcraft.monthly-challenge.image', '/custom-challenge.webp');
        $this->setSiteOption('stemcraft.community-builds.1.title', 'Library Lab');
        $this->setSiteOption('stemcraft.community-builds.1.description', 'A shared library build with coding corners.');
        $this->setSiteOption('stemcraft.community-builds.1.image', '/custom-build.webp');

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Build a Solar Workshop')
            ->assertSee('Create a workshop that uses daylight and clever storage.')
            ->assertSee('Include at least one moving part.')
            ->assertSee('custom-challenge.webp', false)
            ->assertSee('Explore imaginative builds, shared projects and creative ideas from across the STEMCraft world.')
            ->assertSee('Library Lab')
            ->assertSee('A shared library build with coding corners.')
            ->assertSee('custom-build.webp', false);
    }

    public function test_stemcraft_content_site_options_are_created_with_media_inputs(): void
    {
        SiteOption::ensureDefaultOptionsExist();

        $this->assertSame('media', SiteOption::inputType('stemcraft.monthly-challenge.image'));
        $this->assertSame('media', SiteOption::inputType('stemcraft.community-builds.1.image'));
        $this->assertDatabaseHas('site_options', [
            'name' => 'stemcraft.monthly-challenge.title',
            'value' => 'Build your dream treehouse',
        ]);
        $this->assertDatabaseMissing('site_options', [
            'name' => 'stemcraft.community-builds.introduction',
        ]);
        $this->assertDatabaseHas('site_options', [
            'name' => 'stemcraft.faqs.items',
        ]);
    }

    public function test_stemcraft_faqs_are_editable_and_landing_page_uses_selected_items(): void
    {
        $this->setSiteOption('stemcraft.faqs.items', json_encode([
            [
                'question' => 'Full FAQ first?',
                'answer' => 'This answer appears only on the full FAQ page.',
                'show_on_index' => false,
            ],
            [
                'question' => 'Landing FAQ second?',
                'answer' => 'This answer appears on the landing page.',
                'show_on_index' => true,
            ],
            [
                'question' => 'Landing FAQ third?',
                'answer' => 'This answer also appears on the landing page.',
                'show_on_index' => true,
            ],
        ], JSON_THROW_ON_ERROR));

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Landing FAQ second?',
                'Landing FAQ third?',
            ])
            ->assertDontSee('Full FAQ first?');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSeeInOrder([
                'Full FAQ first?',
                'Landing FAQ second?',
                'Landing FAQ third?',
            ]);
    }

    private function setSiteOption(string $name, string $value): void
    {
        SiteOption::query()->updateOrCreate(['name' => $name], ['value' => $value]);
    }
}
