<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminStemcraftContentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_edit_stemcraft_landing_content_from_dedicated_page(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.stemcraft-content.edit'))
            ->assertOk()
            ->assertSee('STEMCraft Content')
            ->assertSee('Monthly Challenge')
            ->assertSee('Community Builds')
            ->assertSee('Build Card 1 Title')
            ->assertSee('Build Card 2 Image')
            ->assertSee('Build Card 3 Description')
            ->assertSee('name="builds[1][title]"', false)
            ->assertDontSee('<x-ui.input', false)
            ->assertDontSee('<x-ui.media', false)
            ->assertSee('Select Image');

        $this->actingAs($admin)
            ->put(route('admin.stemcraft-content.update'), [
                'monthly_challenge' => [
                    'title' => 'Build a Solar Workshop',
                    'description' => "Create a workshop that uses **daylight** and clever storage.\n\n- Add a roof window\n- Include a tool wall",
                    'prompt' => 'Include at least one **moving part**.',
                    'image' => '/solar-workshop.webp',
                    'image_alt' => 'A solar workshop STEMCraft build',
                ],
                'builds' => [
                    1 => [
                        'title' => 'Library Lab',
                        'description' => 'A shared library build with coding corners.',
                        'image' => '/library-lab.webp',
                        'image_alt' => 'A STEMCraft library lab build',
                    ],
                    2 => [
                        'title' => 'Garden Machine',
                        'description' => 'A working garden machine with moving parts.',
                        'image' => '/garden-machine.webp',
                        'image_alt' => 'A garden machine STEMCraft build',
                    ],
                    3 => [
                        'title' => 'Bridge Test',
                        'description' => 'A bridge design tested with different supports.',
                        'image' => '/bridge-test.webp',
                        'image_alt' => 'A bridge STEMCraft build',
                    ],
                ],
                'faqs' => [
                    [
                        'question' => 'Second question?',
                        'answer' => 'This appears second on the full FAQ page.',
                        'show_on_index' => '0',
                    ],
                    [
                        'question' => 'First landing question?',
                        'answer' => 'This appears first and on the landing page.',
                        'show_on_index' => '1',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.stemcraft-content.edit'));

        $this->assertSame('Build a Solar Workshop', SiteOption::value('stemcraft.monthly-challenge.title'));
        $this->assertStringContainsString('**daylight**', (string) SiteOption::value('stemcraft.monthly-challenge.description'));
        $this->assertSame('/solar-workshop.webp', SiteOption::value('stemcraft.monthly-challenge.image'));
        $this->assertSame('Library Lab', SiteOption::value('stemcraft.community-builds.1.title'));
        $this->assertSame('/library-lab.webp', SiteOption::value('stemcraft.community-builds.1.image'));
        $this->assertStringContainsString('First landing question?', (string) SiteOption::value('stemcraft.faqs.items'));

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSee('Build a Solar Workshop')
            ->assertSee('<strong>daylight</strong>', false)
            ->assertSee('<li>Add a roof window</li>', false)
            ->assertSee('<strong>moving part</strong>', false)
            ->assertDontSee('**daylight**')
            ->assertSee('Explore imaginative builds, shared projects and creative ideas from across the STEMCraft world.')
            ->assertSee('Library Lab')
            ->assertSee('First landing question?')
            ->assertDontSee('Second question?');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSeeInOrder([
                'Second question?',
                'First landing question?',
            ]);
    }

    public function test_admin_navigation_links_to_stemcraft_content_page(): void
    {
        $this->actingAs($this->createAdminUser())
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('STEMCraft')
            ->assertSee(route('admin.stemcraft-content.edit'), false);
    }

    public function test_admin_can_reorder_and_select_stemcraft_faqs_for_landing_page(): void
    {
        $this->actingAs($this->createAdminUser())
            ->put(route('admin.stemcraft-content.update'), $this->validContentPayload([
                'faqs' => [
                    [
                        'question' => 'Full page only?',
                        'answer' => 'This answer stays on the FAQ page.',
                        'show_on_index' => '0',
                    ],
                    [
                        'question' => 'Landing page question?',
                        'answer' => 'This answer appears on both pages.',
                        'show_on_index' => '1',
                    ],
                    [
                        'question' => 'Another landing question?',
                        'answer' => 'This answer also appears on both pages.',
                        'show_on_index' => '1',
                    ],
                ],
            ]))
            ->assertRedirect(route('admin.stemcraft-content.edit'));

        $this->get(route('stemcraft.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'Landing page question?',
                'Another landing question?',
            ])
            ->assertDontSee('Full page only?');

        $this->get(route('stemcraft.faqs'))
            ->assertOk()
            ->assertSeeInOrder([
                'Full page only?',
                'Landing page question?',
                'Another landing question?',
            ]);
    }

    private function createAdminUser(): User
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'admin']);

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function validContentPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'monthly_challenge' => [
                'title' => 'Build a Solar Workshop',
                'description' => 'Create a workshop that uses daylight and clever storage.',
                'prompt' => 'Include at least one moving part.',
                'image' => '/solar-workshop.webp',
                'image_alt' => 'A solar workshop STEMCraft build',
            ],
            'builds' => [
                1 => [
                    'title' => 'Library Lab',
                    'description' => 'A shared library build with coding corners.',
                    'image' => '/library-lab.webp',
                    'image_alt' => 'A STEMCraft library lab build',
                ],
                2 => [
                    'title' => 'Garden Machine',
                    'description' => 'A working garden machine with moving parts.',
                    'image' => '/garden-machine.webp',
                    'image_alt' => 'A garden machine STEMCraft build',
                ],
                3 => [
                    'title' => 'Bridge Test',
                    'description' => 'A bridge design tested with different supports.',
                    'image' => '/bridge-test.webp',
                    'image_alt' => 'A bridge STEMCraft build',
                ],
            ],
            'faqs' => [
                [
                    'question' => 'How do I join?',
                    'answer' => 'Use the join page.',
                    'show_on_index' => '1',
                ],
            ],
        ], $overrides);
    }
}
