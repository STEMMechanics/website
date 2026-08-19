<?php

namespace Tests\Feature;

use App\Models\NewsletterStoreTheme;
use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewsletterStoreThemeAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_default_store_themes_are_installed(): void
    {
        $this->assertSame(
            ['New Kit Arrivals', 'Grab Extras', 'Back in Stock', 'Recently Updated', 'Featured Picks'],
            NewsletterStoreTheme::query()->orderBy('sort_order')->pluck('name')->all(),
        );
        $this->assertSame(['materials', 'parts'], NewsletterStoreTheme::query()->where('name', 'Grab Extras')->firstOrFail()->category_slugs);
    }

    public function test_admin_can_create_update_and_delete_a_store_theme(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $category = ProductCategory::factory()->create(['name' => 'Kits', 'slug' => 'kits']);

        $this->actingAs($admin)->post(route('admin.subscription.theme.store'), [
            'name' => 'This Week in Kits',
            'title' => 'Fresh this week',
            'intro' => 'New projects to explore.',
            'category_slugs' => [$category->slug],
            'match_type' => 'created_within',
            'match_days' => 7,
            'sort_order' => 60,
            'is_active' => 1,
        ])->assertRedirect();

        $theme = NewsletterStoreTheme::query()->where('name', 'This Week in Kits')->firstOrFail();
        $this->assertSame(7, $theme->match_days);
        $this->actingAs($admin)->get(route('admin.subscription.theme.index'))->assertOk()->assertSee('This Week in Kits');

        $this->actingAs($admin)->put(route('admin.subscription.theme.update', $theme), [
            'name' => 'Random Kits',
            'title' => 'Try something different',
            'category_slugs' => [$category->slug],
            'match_type' => 'random',
            'match_days' => 7,
            'sort_order' => 60,
            'is_active' => 1,
        ])->assertRedirect();

        $this->assertNull($theme->fresh()->match_days);
        $this->actingAs($admin)->delete(route('admin.subscription.theme.destroy', $theme))->assertRedirect(route('admin.subscription.theme.index'));
        $this->assertModelMissing($theme);
    }
}
