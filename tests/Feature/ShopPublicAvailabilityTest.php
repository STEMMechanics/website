<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Product;
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
}
