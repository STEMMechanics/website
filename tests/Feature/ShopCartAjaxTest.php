<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopCartAjaxTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_pages_render_ajax_cart_controls(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSee('handleAddToCart($event.target)', false);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ]);

        $this->get(route('shop.cart.show'))
            ->assertOk()
            ->assertSee('changeCartQuantity(line.key', false)
            ->assertSee('removeCartLine(line.key)', false);
    }

    public function test_store_listing_renders_an_add_to_cart_chooser_for_multi_option_products(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 8,
            'base_variant_name' => 'Starter Kit',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 34.95,
            'inventory_quantity' => 6,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeText('Class Kit')
            ->assertSeeText('Add to Cart')
            ->assertSeeText('Choose a variant')
            ->assertSee('x-teleport="body"', false)
            ->assertSee('shop-catalog-option-control', false)
            ->assertSeeText('Class Kit')
            ->assertDontSeeText('Quantity to add')
            ->assertDontSeeText('Add Selected Variant')
            ->assertSee('handleAddToCart($event.target)', false)
            ->assertSee('chooseOption(String(', false)
            ->assertSee('changeCartQuantity(cartQuantity() - 1)', false)
            ->assertSee('product_variant_id', false);
    }

    public function test_store_listing_uses_option_summary_for_multi_option_backorder_products(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Robotics Pack',
            'price' => 49.95,
            'inventory_quantity' => 5,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => now()->addWeek(),
            'base_variant_name' => 'Starter Pack',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Advanced Pack',
            'price' => 69.95,
            'inventory_quantity' => 3,
        ]);

        $this->get(route('shop.index'))
            ->assertOk()
            ->assertSeeText('Robotics Pack')
            ->assertSeeText('2 options available')
            ->assertDontSeeText('Pre-order available.')
            ->assertDontSeeText('Pre-order');
    }

    public function test_cart_endpoints_return_json_payloads_for_ajax_requests(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 19.95,
            'inventory_quantity' => 10,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $addResponse = $this->withHeaders($headers)->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ]);

        $addResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.is_empty', false)
            ->assertJsonPath('cart.summary.item_count', 2)
            ->assertJsonPath('cart.lines.0.quantity', 2);
        $this->assertFalse(session()->has('message'));

        $lineKey = (string) $addResponse->json('cart.lines.0.key');
        $this->assertNotSame('', $lineKey);

        $updateResponse = $this->withHeaders($headers)->post(route('shop.cart.update'), [
            'quantities' => [$lineKey => 3],
            'shipping_country' => 'Australia',
        ]);

        $updateResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.item_count', 3)
            ->assertJsonPath('cart.lines.0.quantity', 3);
        $this->assertFalse(session()->has('message'));

        $removeResponse = $this->withHeaders($headers)->post(route('shop.cart.remove'), [
            'line_key' => $lineKey,
            'shipping_country' => 'Australia',
        ]);

        $removeResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.is_empty', true)
            ->assertJsonPath('cart.summary.item_count', 0)
            ->assertJsonCount(0, 'cart.lines');
        $this->assertFalse(session()->has('message'));
    }

    public function test_digital_cart_endpoints_allow_multiple_quantities(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $addResponse = $this->withHeaders($headers)->post(route('shop.cart.add', $product), [
            'quantity' => 4,
        ]);

        $addResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.item_count', 4)
            ->assertJsonPath('cart.lines.0.quantity', 4)
            ->assertJsonPath('cart.lines.0.max_quantity', 99)
            ->assertJsonPath('cart.lines.0.is_digital', true);

        $lineKey = (string) $addResponse->json('cart.lines.0.key');

        $this->withHeaders($headers)->post(route('shop.cart.update'), [
            'quantities' => [$lineKey => 7],
            'shipping_country' => 'Australia',
        ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.item_count', 7)
            ->assertJsonPath('cart.lines.0.quantity', 7)
            ->assertJsonPath('cart.lines.0.max_quantity', 99);
    }

    public function test_cart_payload_notifies_when_stock_has_changed(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 29.95,
            'inventory_quantity' => 2,
            'shipping_units' => 1,
            'min_satchel_rank' => 1,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $product->update([
            'inventory_quantity' => 1,
        ]);

        $response = $this->getJson(route('shop.cart.show'));

        $response
            ->assertOk()
            ->assertJsonPath('cart.lines.0.quantity', 1)
            ->assertJsonPath('cart.inventory_change_notices.0.type', 'reduced');

        $this->assertStringContainsString(
            'Quantity for '.$product->title.' was reduced from 2 to 1 because stock changed.',
            (string) $response->json('cart.inventory_change_notices.0.message')
        );
    }

    public function test_cart_payload_uses_the_named_base_option_when_variants_exist(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => null,
            'shipping_units' => 1.00,
            'min_satchel_rank' => 1,
            'base_variant_name' => 'Starter Kit',
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 29.95,
            'shipping_units' => 2.50,
            'inventory_quantity' => 4,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->getJson(route('shop.cart.show'))
            ->assertOk()
            ->assertJsonPath('cart.lines.0.display_title', 'Class Kit - Starter Kit')
            ->assertJsonPath('cart.lines.0.variant_name', 'Starter Kit');
    }

    public function test_selected_variant_can_be_added_in_multiple_quantities(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 10,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 29.95,
            'inventory_quantity' => 8,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->withHeaders($headers)
            ->post(route('shop.cart.add', $product), [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.item_count', 3)
            ->assertJsonPath('cart.lines.0.display_title', 'Class Kit - Extended Kit')
            ->assertJsonPath('cart.lines.0.variant_name', 'Extended Kit')
            ->assertJsonPath('cart.lines.0.quantity', 3);
    }

    public function test_physical_variant_without_inventory_cannot_be_added_when_not_preorder_or_backorder(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 4,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 29.95,
            'inventory_quantity' => null,
        ]);

        $this->from(route('shop.product.show', $product))
            ->post(route('shop.cart.add', $product), [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertRedirect(route('shop.product.show', $product))
            ->assertSessionHasErrors('product_variant_id');
    }

    public function test_backorder_variant_without_inventory_can_be_purchased_when_variant_allows_backorder(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 29.95,
            'inventory_quantity' => null,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-05-01',
        ]);

        $this->post(route('shop.cart.add', $product), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $this->getJson(route('shop.cart.show'))
            ->assertOk()
            ->assertJsonPath('cart.lines.0.display_title', 'Class Kit - Extended Kit')
            ->assertJsonPath('cart.lines.0.quantity', 2)
            ->assertJsonPath('cart.lines.0.available_now_quantity', 0)
            ->assertJsonPath('cart.lines.0.delayed_quantity', 2)
            ->assertJsonPath('cart.lines.0.delayed_fulfilment_type', 'backorder');
    }

    public function test_legacy_preorder_variant_without_inventory_is_purchased_as_backorder(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 0,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 29.95,
            'inventory_quantity' => null,
            'is_preorder' => true,
            'preorder_shipping_estimate' => '2026-05-15',
        ]);

        $this->post(route('shop.cart.add', $product), [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $this->getJson(route('shop.cart.show'))
            ->assertOk()
            ->assertJsonPath('cart.lines.0.display_title', 'Class Kit - Extended Kit')
            ->assertJsonPath('cart.lines.0.quantity', 2)
            ->assertJsonPath('cart.lines.0.available_now_quantity', 0)
            ->assertJsonPath('cart.lines.0.delayed_quantity', 2)
            ->assertJsonPath('cart.lines.0.delayed_fulfilment_type', 'backorder')
            ->assertJsonPath('cart.lines.0.is_preorder', false);
    }

    public function test_cart_payload_sorts_lines_by_product_then_variant_order(): void
    {
        $alpha = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Alpha Kit',
            'price' => 24.95,
            'inventory_quantity' => 12,
            'base_variant_name' => 'Starter Kit',
        ]);
        $alphaExtended = ProductVariant::factory()->create([
            'product_id' => $alpha->id,
            'name' => 'Extended Kit',
            'price' => 34.95,
            'inventory_quantity' => 6,
            'sort_order' => 2,
        ]);
        $alphaCompact = ProductVariant::factory()->create([
            'product_id' => $alpha->id,
            'name' => 'Compact Kit',
            'price' => 29.95,
            'inventory_quantity' => 6,
            'sort_order' => 1,
        ]);
        $beta = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Beta Kit',
            'price' => 19.95,
            'inventory_quantity' => 10,
        ]);

        $this->post(route('shop.cart.add', $beta), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->post(route('shop.cart.add', $alpha), [
            'product_variant_id' => $alphaExtended->id,
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->post(route('shop.cart.add', $alpha), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->post(route('shop.cart.add', $alpha), [
            'product_variant_id' => $alphaCompact->id,
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $lines = $this->getJson(route('shop.cart.show'))
            ->assertOk()
            ->json('cart.lines');

        $this->assertSame([
            'Alpha Kit - Starter Kit',
            'Alpha Kit - Compact Kit',
            'Alpha Kit - Extended Kit',
            'Beta Kit',
        ], array_column($lines, 'display_title'));
        $this->assertSame([
            'Starter Kit',
            'Compact Kit',
            'Extended Kit',
            null,
        ], array_column($lines, 'variant_name'));
        $this->assertArrayNotHasKey('shipping_label', $lines[0]);
    }

    public function test_coupon_endpoints_return_json_payloads_for_ajax_requests(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 25.00,
        ]);

        Coupon::factory()->create([
            'code' => 'SAVE10',
            'amount' => 10.00,
        ]);

        $headers = [
            'Accept' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $this->withHeaders($headers)->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertOk();

        $applyResponse = $this->withHeaders($headers)->post(route('shop.cart.coupon.apply'), [
            'coupon_code' => 'SAVE10',
            'shipping_country' => 'Australia',
            'return_to' => route('shop.cart.show'),
        ]);

        $applyResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.coupon_code', 'SAVE10')
            ->assertJsonPath('cart.summary.discount', 10);

        $removeResponse = $this->withHeaders($headers)->post(route('shop.cart.coupon.remove'), [
            'shipping_country' => 'Australia',
            'return_to' => route('shop.cart.show'),
        ]);

        $removeResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('cart.summary.coupon_code', null)
            ->assertJsonPath('cart.summary.discount', 0);
    }
}
