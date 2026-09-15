<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class NavbarThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_test_site_uses_a_tinted_navbar_background(): void
    {
        $html = $this->renderNavbarForHost('test.stemmechanics.com.au');

        $this->assertStringContainsString('class="relative z-120 isolate border-b border-purple-300 shadow bg-purple-50"', $html);
        $this->assertStringContainsString('repeating-linear-gradient(45deg', $html);
    }

    public function test_non_test_sites_keep_the_default_white_navbar_background(): void
    {
        $html = $this->renderNavbarForHost('localhost');

        $this->assertStringContainsString('class="relative z-120 isolate shadow bg-white"', $html);
        $this->assertStringNotContainsString('bg-purple-50', $html);
        $this->assertStringNotContainsString('repeating-linear-gradient(45deg', $html);
    }

    public function test_admin_nav_uses_categories_label_for_workshop_categories(): void
    {
        $this->actingAs($this->createAdminUser());

        $html = $this->renderNavbarForHost('localhost');

        $this->assertStringContainsString('Workshops &amp; Community', $html);
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('admin.workshop-category.index'), '/').'".*?>\s*<i[^>]*><\/i>\s*<span[^>]*>Categories<\/span>/s',
            $html,
        );
    }

    public function test_product_attention_badge_and_stock_notice_use_the_combined_attention_count(): void
    {
        $this->actingAs($this->createAdminUser());
        Product::factory()->create(['price' => 11, 'inventory_quantity' => 1]);
        $this->get(route('admin.shop.product.index'))->assertOk()
            ->assertSee('1 product needs stock, order or allocation attention')
            ->assertSee('Low stock needs review')
            ->assertSee('Allocation needs review')
            ->assertSee('1 with low stock or orders awaiting fulfilment');
    }

    private function renderNavbarForHost(string $host): string
    {
        $request = Request::create('https://'.$host.'/', 'GET');
        $request->server->set('HTTP_HOST', $host);
        $request->server->set('SERVER_NAME', $host);

        $this->app->instance('request', $request);

        return view('components.navbar')->render();
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
