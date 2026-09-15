<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopProductVariantLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_variant_sku_path_opens_the_parent_product_with_the_variant_selected(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE, 'slug' => '3d-mini-pigs']);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => '3D-MINI-PIGS-25']);
        $this->assertSame('3d-mini-pigs-25', $variant->url_slug);

        $this->get('/store/3d-mini-pigs-25')
            ->assertOk()
            ->assertViewHas('product', fn (Product $shown): bool => $shown->is($product))
            ->assertViewHas('linkedVariantId', $variant->id)
            ->assertSee("selectedVariantId: '".$variant->id."'", false);
        $this->get(route('shop.product.show', [$product, 'variant' => '3d-mini-pigs-25']))
            ->assertOk()->assertViewHas('linkedVariantId', $variant->id);
    }

    public function test_variant_paths_are_unique_and_do_not_shadow_product_or_system_pages(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE, 'slug' => 'mini-pigs']);
        $first = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'Mini Pigs']);
        $second = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'mini-pigs']);
        $reserved = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'cart']);
        $this->assertSame('mini-pigs-2', $first->url_slug);
        $this->assertSame('mini-pigs-3', $second->url_slug);
        $this->assertSame('cart-2', $reserved->url_slug);
        $laterProduct = Product::factory()->create(['status' => Product::STATUS_ACTIVE, 'slug' => 'mini-pigs-2']);
        $this->assertNotSame($first->url_slug, $laterProduct->slug);
        $this->get('/store/mini-pigs')->assertOk()->assertViewHas('linkedVariantId', null);
        $this->get('/store/mini-pigs-2')->assertOk()->assertViewHas('linkedVariantId', $first->id);
    }

    public function test_variant_urls_stay_stable_and_copies_receive_a_new_url(): void
    {
        $variant = ProductVariant::factory()->create(['sku' => 'PIG-25']);
        $variant->update(['name' => 'Renamed', 'sku' => 'NEW-SKU']);
        $this->assertSame('pig-25', $variant->fresh()->url_slug);
        $copy = $variant->replicate();
        $copy->sku = 'PIG-25-COPY';
        $copy->save();
        $this->assertSame('pig-25-copy', $copy->url_slug);
    }

    public function test_inactive_variants_and_inactive_parent_products_have_no_public_variant_page(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'hidden-option', 'is_active' => false]);
        $this->get('/store/hidden-option')->assertNotFound();
        $variant->update(['is_active' => true]);
        $product->update(['status' => Product::STATUS_DRAFT]);
        $this->get('/store/hidden-option')->assertNotFound();
        $this->get('/store/no-such-product-or-variant')->assertNotFound();
    }

    public function test_shared_link_selects_the_requested_variant_even_when_out_of_stock(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 0]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 1, 'inventory_quantity' => 0]);

        $this->get(route('shop.product.show', [$product, 'variant' => $variant->id]))
            ->assertOk()
            ->assertViewHas('linkedVariantId', $variant->id)
            ->assertSee("selectedVariantId: '".$variant->id."'", false)
            ->assertSee('rel="canonical" href="'.route('shop.product.show', $product).'"', false);
    }

    public function test_invalid_inactive_deleted_and_other_product_variants_fall_back_to_base(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        $inactive = ProductVariant::factory()->create(['product_id' => $product->id, 'is_active' => false]);
        $foreign = ProductVariant::factory()->create();
        $deleted = ProductVariant::factory()->create(['product_id' => $product->id]);
        $deleted->delete();

        foreach ([$inactive->id, $foreign->id, $deleted->id, 'invalid', ['123'], '', '-1'] as $value) {
            $this->get(route('shop.product.show', [$product, 'variant' => $value]))
                ->assertOk()
                ->assertViewHas('linkedVariantId', null)
                ->assertSee("selectedVariantId: ''", false);
        }
    }

    public function test_plain_product_links_keep_the_base_selection(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        ProductVariant::factory()->create(['product_id' => $product->id]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertViewHas('linkedVariantId', null)
            ->assertSee("selectedVariantId: ''", false);
    }

    public function test_validation_redirect_keeps_the_submitted_selection_ahead_of_the_link(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        $linked = ProductVariant::factory()->create(['product_id' => $product->id]);
        $submitted = ProductVariant::factory()->create(['product_id' => $product->id]);

        foreach ([(string) $submitted->id, ''] as $selection) {
            $this->withSession(['_old_input' => ['product_variant_id' => $selection]])
                ->get(route('shop.product.show', [$product, 'variant' => $linked->id]))
                ->assertOk()
                ->assertSee("selectedVariantId: '".$selection."'", false);
        }
    }
}
