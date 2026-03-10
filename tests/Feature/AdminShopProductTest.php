<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_shop_product(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.create'))
            ->assertOk()
            ->assertSee('Create Product');

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Laser Cut Catapult Kit',
                'slug' => 'laser-cut-catapult-kit',
                'category' => 'Kits',
                'sku' => 'CAT-100',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'short_description' => 'A build-at-home launcher kit.',
                'description' => 'Includes all timber parts and instructions.',
                'price' => '24.95',
                'compare_at_price' => '29.95',
                'inventory_quantity' => '12',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '3',
                'weight_grams' => '820',
                'box_only' => '0',
                'sort_order' => '5',
                'is_featured' => '1',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'slug' => 'laser-cut-catapult-kit',
            'category' => 'Kits',
            'sku' => 'CAT-100',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'shipping_units' => 1.00,
            'min_satchel_rank' => 3,
            'weight_grams' => 820,
            'box_only' => 0,
            'is_featured' => 1,
            'tax_rate' => 0.1000,
        ]);
    }
}
