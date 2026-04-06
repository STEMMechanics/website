<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\UserGroup;
use App\Models\SiteOption;
use App\Models\User;
use App\Support\ShopAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPublicAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_store_links_are_disabled_when_no_products_are_on_sale(): void
    {
        Product::factory()->create([
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertDontSee('href="'.route('shop.index').'"', false)
            ->assertDontSee('>Store</a>', false)
            ->assertDontSee('aria-label="Store unavailable"', false);
    }

    public function test_public_store_routes_show_the_unavailable_page_when_no_products_are_on_sale(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_DRAFT,
        ]);

        $this->get(route('shop.index'))
            ->assertStatus(503)
            ->assertSee('Store Temporarily Unavailable');

        $this->get(route('shop.cart.show'))
            ->assertStatus(503)
            ->assertSee('Store Temporarily Unavailable');

        $this->get(route('shop.product.show', $product))
            ->assertStatus(503)
            ->assertSee('Store Temporarily Unavailable');

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.index'));
    }

    public function test_global_store_disable_blocks_public_store_even_with_active_products(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
        ]);

        SiteOption::query()->create([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
            'value' => '0',
        ]);

        $this->get(route('index'))
            ->assertOk()
            ->assertDontSee('href="'.route('shop.index').'"', false)
            ->assertDontSee('>Store</a>', false);

        $this->get(route('shop.index'))
            ->assertStatus(503)
            ->assertSee('Store Temporarily Unavailable');

        $this->get(route('shop.product.show', $product))
            ->assertStatus(503)
            ->assertSee('Store Temporarily Unavailable');
    }

    public function test_legacy_shop_url_redirects_to_store_url(): void
    {
        $this->get('/shop')->assertRedirect('/store');
    }

    public function test_public_store_lists_free_digital_products_as_instant_downloads(): void
    {
        Product::factory()->create([
            'title' => 'Digital Guide',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 0,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeText('Digital Guide')
            ->assertSeeText('Free')
            ->assertSeeText('Instant download after checkout')
            ->assertDontSeeText('In stock');
    }

    public function test_public_store_marks_low_stock_products_with_warning_badge(): void
    {
        $product = Product::factory()->create([
            'title' => 'Low Stock Kit',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 19.95,
            'inventory_quantity' => 2,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeText('Low Stock Kit')
            ->assertSeeText('Low stock')
            ->assertSee('fa-triangle-exclamation', false)
            ->assertSee('text-amber-700', false);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('Low stock')
            ->assertSee('fa-triangle-exclamation', false)
            ->assertSee('text-amber-700', false);
    }

    public function test_public_store_product_cards_request_a_larger_image_variant(): void
    {
        $user = User::factory()->create();
        $hero = Media::factory()->create([
            'name' => 'product-card.jpg',
            'title' => 'Product Card Hero',
            'mime_type' => 'image/jpeg',
            'user_id' => $user->id,
        ]);

        Product::factory()->create([
            'title' => 'Display Kit',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'hero_media_name' => $hero->name,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSee('?md', false);
    }

    public function test_public_store_renders_product_subtitles_on_cards_and_product_pages(): void
    {
        $product = Product::factory()->create([
            'title' => 'Bundle Pack',
            'subtitle' => '25 pack',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeText('25 pack');

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('25 pack');
    }

    public function test_public_store_marks_the_top_three_products_as_best_sellers_based_on_recent_sales(): void
    {
        $topOne = Product::factory()->create([
            'title' => 'Top One',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $topTwo = Product::factory()->create([
            'title' => 'Top Two',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $topThree = Product::factory()->create([
            'title' => 'Top Three',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $notTopThree = Product::factory()->create([
            'title' => 'Not Top Three',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        $oldWinner = Product::factory()->create([
            'title' => 'Old Winner',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => StoreOrder::factory()->create([
                'status' => StoreOrder::STATUS_PROCESSING,
                'paid_at' => now()->subDays(2),
            ])->id,
            'product_id' => $topOne->id,
            'quantity' => 5,
            'available_now_quantity' => 5,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => StoreOrder::factory()->create([
                'status' => StoreOrder::STATUS_SHIPPED,
                'paid_at' => now()->subDays(3),
            ])->id,
            'product_id' => $topTwo->id,
            'quantity' => 4,
            'available_now_quantity' => 4,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => StoreOrder::factory()->create([
                'status' => StoreOrder::STATUS_FULFILLED,
                'paid_at' => now()->subDays(1),
            ])->id,
            'product_id' => $topThree->id,
            'quantity' => 3,
            'available_now_quantity' => 3,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => StoreOrder::factory()->create([
                'status' => StoreOrder::STATUS_PROCESSING,
                'paid_at' => now()->subDays(1),
            ])->id,
            'product_id' => $notTopThree->id,
            'quantity' => 2,
            'available_now_quantity' => 2,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => StoreOrder::factory()->create([
                'status' => StoreOrder::STATUS_PROCESSING,
                'paid_at' => now()->subDays(40),
            ])->id,
            'product_id' => $oldWinner->id,
            'quantity' => 50,
            'available_now_quantity' => 50,
            'delayed_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
        ]);

        $response = $this->get(route('shop.index'));

        $response->assertOk()
            ->assertSee('absolute left-3 top-3', false)
            ->assertSeeText('Best seller')
            ->assertSeeText('Top One')
            ->assertSeeText('Top Two')
            ->assertSeeText('Top Three');

        $this->assertSame(3, substr_count($response->getContent(), 'Best seller'));
    }

    public function test_admins_see_an_inline_edit_link_and_consistent_title_row_height_on_product_cards(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'title' => 'Short Title',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        $this->actingAs($admin)
            ->get(route('shop.index'))
            ->assertOk()
            ->assertSee('min-height: 4rem;', false)
            ->assertSee(route('admin.shop.product.edit', $product), false)
            ->assertSee('aria-label="Edit Short Title"', false);

        $this->actingAs($admin)
            ->get(route('shop.index', ['view' => 'list']))
            ->assertOk()
            ->assertDontSee('min-h-16', false)
            ->assertSee(route('admin.shop.product.edit', $product), false)
            ->assertSee('aria-label="Edit Short Title"', false);
    }
}
