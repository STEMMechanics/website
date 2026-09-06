<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\HomeHero;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HomeHeroEditorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        return $user;
    }

    public function test_defaults_editor_and_saved_text_are_rendered_safely(): void
    {
        $this->get(route('index'))->assertOk()->assertSee(HomeHero::defaults()['heading'])->assertSee('home-hero-1024.webp');
        $this->actingAs($this->admin())->get(route('admin.site_option.hero'))->assertOk()->assertSee('Live preview')->assertSee('Select Image');
        $this->put(route('admin.site_option.hero.update'), ['heading' => '<script>alert(1)</script>', 'eyebrow' => 'Come explore', 'body' => "New introduction\n\nSecond paragraph", 'caption' => 'Our workshop'])
            ->assertRedirect(route('admin.site_option.hero'));
        $this->assertSame("New introduction\n\nSecond paragraph", HomeHero::content()['body']);
        $this->get(route('index'))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false)->assertSee('Come explore');
    }

    public function test_settings_tabs_paragraph_compatibility_and_advanced_menu(): void
    {
        SiteOption::create(['name' => HomeHero::OPTION, 'value' => json_encode(['paragraph_one' => 'First saved paragraph', 'paragraph_two' => 'Second saved paragraph'])]);
        $this->assertSame("First saved paragraph\n\nSecond saved paragraph", HomeHero::content()['body']);
        $this->actingAs($this->admin())->get(route('admin.site_option.hero'))->assertOk()
            ->assertSee('Homepage settings')->assertSee('All settings')->assertSee('name="body"', false)->assertDontSee('First paragraph');
        $this->get(route('admin.site_option.index'))->assertOk()->assertSee('Homepage')->assertSee('site-settings-tools')->assertSee('Advanced settings');
    }

    public function test_hero_rejects_non_public_images_and_non_admin_updates(): void
    {
        $user = $this->admin();
        $media = Media::create(['name' => 'hero-private.png', 'title' => 'Private', 'hash' => str_repeat('e', 64), 'mime_type' => 'image/png', 'size' => 100, 'user_id' => $user->id, 'visibility' => 'private']);
        $this->actingAs($user)->putJson(route('admin.site_option.hero.update'), ['heading' => 'New heading', 'hero_image' => $media->name])->assertUnprocessable();
        $media->update(['visibility' => 'public', 'password' => 'secret']);
        $this->putJson(route('admin.site_option.hero.update'), ['heading' => 'New heading', 'hero_image' => $media->name])->assertUnprocessable();
        $this->assertNull(SiteOption::where('name', HomeHero::OPTION)->first());
        $picker = $this->getJson(route('media.index', ['public_usable_only' => 1, 'passwordless_only' => 1, 'mime_type' => 'image/*']))->assertOk();
        $picker->assertDontSee($media->name);
        $media->update(['password' => null]);
        $this->put(route('admin.site_option.hero.update'), ['heading' => 'New heading', 'hero_image' => $media->name])->assertRedirect();
        $this->assertSame($media->name, HomeHero::content()['image']);
        $usage = app(\App\Services\MediaUsageService::class);
        $this->assertContains($media->name, $usage->usedMediaNames());
        $this->assertTrue(collect($usage->usagesFor($media->name))->contains(fn ($item) => $item['type'] === 'Homepage hero' && $item['public']));
        $this->get(route('index'))->assertOk()->assertSee(route('media.download', $media), false);
        $this->actingAs(User::factory()->create())->putJson(route('admin.site_option.hero.update'), ['heading' => 'Unauthorised'])->assertForbidden();
        $this->assertSame('New heading', HomeHero::content()['heading']);
    }
}
