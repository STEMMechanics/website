<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaQualitySiteOptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_quality_options_preserve_the_existing_defaults(): void
    {
        SiteOption::ensureDefaultOptionsExist();

        $this->assertSame('75', SiteOption::defaultValue('media.variant-quality'));
        $this->assertSame('90', SiteOption::defaultValue('media.editor-quality'));
        $this->assertSame('84', SiteOption::defaultValue('media.flyer-quality'));
        $this->assertSame('number', SiteOption::inputType('media.variant-quality'));
        $this->assertSame('number', SiteOption::inputType('media.editor-quality'));
        $this->assertSame('number', SiteOption::inputType('media.flyer-quality'));
        $this->assertDatabaseHas('site_options', ['name' => 'media.variant-quality', 'value' => '75']);
        $this->assertDatabaseHas('site_options', ['name' => 'media.editor-quality', 'value' => '90']);
        $this->assertDatabaseHas('site_options', ['name' => 'media.flyer-quality', 'value' => '84']);
    }

    public function test_admin_media_quality_options_are_limited_to_one_to_one_hundred(): void
    {
        SiteOption::ensureDefaultOptionsExist();
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);

        foreach (['media.variant-quality', 'media.editor-quality', 'media.flyer-quality'] as $name) {
            $option = SiteOption::query()->where('name', $name)->firstOrFail();

            $this->actingAs($admin)
                ->putJson(route('admin.site_option.update', $option), ['value' => '0'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('value');

            $this->putJson(route('admin.site_option.update', $option), ['value' => '101'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors('value');

            $this->putJson(route('admin.site_option.update', $option), ['value' => '88'])
                ->assertOk();

            $this->assertSame(88, SiteOption::intValue($name));
        }
    }
}
