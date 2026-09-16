<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\StoreShippingMethod;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPostagePreviewTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
    }

    public function test_preview_uses_rotated_box_fit_and_calculated_cost_and_excludes_pickup(): void
    {
        $this->admin();
        StoreShippingMethod::query()->update(['is_active' => false]);
        $method = StoreShippingMethod::factory()->create(['name' => 'Post', 'cubic_divisor' => 4000, 'calculated_packaging_cost' => 2,
            'weight_tiers' => [['max_weight_grams' => 1000, 'price' => 5]]]);
        $method->packageOptions()->create(['code' => 'small', 'label' => 'Small box', 'capacity' => 1, 'price' => 6,
            'internal_length_mm' => 200, 'internal_width_mm' => 100, 'internal_height_mm' => 50, 'max_weight_grams' => 500, 'is_active' => true]);
        StoreShippingMethod::factory()->create(['is_pickup' => true]);
        $measurements = ['length_mm' => 50, 'width_mm' => 200, 'height_mm' => 100, 'weight_grams' => 500];
        $this->postJson(route('admin.shop.product.postage-preview'), $measurements)->assertOk()
            ->assertJsonCount(1, 'options')->assertJsonPath('options.0.package', 'Small box')->assertJsonPath('options.0.amount', 6)
            ->assertJsonPath('options.0.fits', true);
        $this->postJson(route('admin.shop.product.postage-preview'), array_replace($measurements, ['weight_grams' => 600]))->assertOk()
            ->assertJsonPath('options.0.package', 'Calculated box up to 1000 g')->assertJsonPath('options.0.amount', 7)
            ->assertJsonPath('options.0.chargeable_weight_grams', 600)->assertJsonPath('options.0.packaging_cost', 2);
        $this->postJson(route('admin.shop.product.postage-preview'), array_replace($measurements, ['weight_grams' => 1001]))->assertOk()
            ->assertJsonPath('options.0.fits', false);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_cubic_weight_can_exceed_the_highest_tier_for_a_single_item(): void
    {
        $this->admin();
        StoreShippingMethod::query()->update(['is_active' => false]);
        StoreShippingMethod::factory()->create(['cubic_divisor' => 4000, 'weight_tiers' => [['max_weight_grams' => 1000, 'price' => 5]]]);
        $this->postJson(route('admin.shop.product.postage-preview'), ['length_mm' => 200, 'width_mm' => 200, 'height_mm' => 200, 'weight_grams' => 100])
            ->assertOk()->assertJsonPath('options.0.fits', false);
    }

    public function test_preview_is_admin_only_and_requires_valid_measurements(): void
    {
        $this->postJson(route('admin.shop.product.postage-preview'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->create())->postJson(route('admin.shop.product.postage-preview'))->assertForbidden();
        $this->admin();
        $this->postJson(route('admin.shop.product.postage-preview'), ['length_mm' => 'abc', 'width_mm' => -1, 'height_mm' => 0, 'weight_grams' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors(['length_mm', 'width_mm', 'height_mm', 'weight_grams']);
        $product = Product::factory()->create();
        $this->get(route('admin.shop.product.edit', $product))->assertOk()->assertSee('Postage fit')->assertSee('basePackedLength');
    }
}
