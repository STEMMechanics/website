<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\StoreOrderItem;
use App\Models\StoreShippingMethod;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\InvoiceInventory;
use App\Services\ProductRecipeService;
use App\Services\ProductVariantUpgrade;
use App\Services\StoreCartService;
use App\Services\StoreOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnifiedProductVariantsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::query()->create(['user_id' => (string) $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    private function payload(Product $product): array
    {
        return [
            ...$product->only(['title', 'slug', 'sku', 'status', 'product_type', 'price', 'inventory_units', 'shared_inventory', 'inventory_quantity', 'unit_cost_ex_tax']),
            'variant_editor' => 1,
            'variants' => $product->variants()->get()->map(fn ($variant) => [
                ...$variant->toArray(), 'is_base' => $variant->id === $product->default_variant_id,
                'original_option' => $variant->id === $product->legacy_base_variant_id,
            ])->all(),
        ];
    }

    public function test_conversion_preserves_the_original_option_and_historical_inventory_reservations(): void
    {
        $product = Product::factory()->create(['base_variant_name' => '2-pack', 'price' => 4.95, 'inventory_units' => 2, 'inventory_quantity' => 7]);
        $item = StoreOrderItem::factory()->create(['product_id' => $product->id, 'product_variant_id' => null, 'unit_price' => 4.95]);
        $invoice = Invoice::factory()->create(['inventory_reservations' => [$product->id.':0' => ['source' => 'product', 'id' => $product->id, 'units' => 1, 'quantity' => 3]]]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'source_type' => Product::class, 'source_id' => $product->id, 'kind' => 'product', 'quantity' => 3, 'details_json' => ['variant_id' => null]]);
        $quote = Quote::factory()->create(['line_items' => [['kind' => 'product', 'source_id' => $product->id, 'source_variant_id' => 0, 'unit_price' => 4.95, 'description' => 'Original quote text']]]);

        $original = app(ProductVariantUpgrade::class)->convert($product);

        $this->assertSame('2-pack', $original->name);
        $this->assertSame($product->sku, $original->sku);
        $this->assertSame(7, $original->inventory_quantity);
        $this->assertNull($product->inventory_quantity);
        $this->assertSame($original->id, $product->default_variant_id);
        $this->assertSame($original->id, $item->fresh()->product_variant_id);
        $this->assertSame('4.95', $item->fresh()->unit_price);
        $this->assertSame($original->id, $line->fresh()->details_json['variant_id']);
        $this->assertSame($original->id, $quote->fresh()->line_items[0]['source_variant_id']);
        $this->assertSame('Original quote text', $quote->fresh()->line_items[0]['description']);
        $this->assertSame('variant', $invoice->fresh()->inventory_reservations[$product->id.':'.$original->id]['source']);
        $this->assertSame($original->id, app(ProductVariantUpgrade::class)->convert($product)->id);
        $this->assertSame(1, $product->variants()->count());

        app(InvoiceInventory::class)->sync($invoice->fresh(), release: true);
        $this->assertSame(10, $original->fresh()->inventory_quantity);
    }

    public function test_rolling_back_the_conversion_preserves_stock_and_historical_references(): void
    {
        $product = Product::factory()->create(['inventory_quantity' => 7]);
        $item = StoreOrderItem::factory()->create(['product_id' => $product->id, 'product_variant_id' => null]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $original->update(['inventory_quantity' => 5]);
        app(ProductVariantUpgrade::class)->restore($product);
        $this->assertNull($product->fresh()->legacy_base_variant_id);
        $this->assertNull($item->fresh()->product_variant_id);
        $this->assertSame(5, $product->fresh()->inventory_quantity);
        $this->assertSame(0, $product->variants()->count());
    }

    public function test_checkout_and_cancellation_keep_the_ordered_variant_when_the_default_changes(): void
    {
        StoreShippingMethod::query()->where('code', 'regular')->update(['pricing_type' => 'cart_value', 'cart_value_tiers' => [['min_cart_value' => 0, 'price' => 12.95]]]);
        $product = Product::factory()->create(['inventory_quantity' => 7, 'price' => 4.95]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $cart = app(StoreCartService::class);
        $cart->add($product, $original, 2);
        $service = app(StoreOrderService::class);
        $order = $service->createFromCart($cart->lines(), [
            'billing_name' => 'Variant Example', 'billing_email' => 'variants@example.com', 'billing_phone' => '0400123456',
            'shipping_name' => 'Variant Example', 'shipping_phone' => '0400123456', 'shipping_address' => '123 Maker Street',
            'shipping_city' => 'Brisbane', 'shipping_state' => 'QLD', 'shipping_postcode' => '4000', 'shipping_country' => 'Australia', 'shipping_method_code' => 'regular',
        ], sendNotifications: false);
        $this->assertSame($original->id, $order->items->first()->product_variant_id);
        $this->assertSame(5, $original->fresh()->inventory_quantity);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_quantity' => 3]);
        $product->update(['default_variant_id' => $large->id]);
        $original->update(['is_active' => false]);
        $service->cancelOrderItemQuantities($order, $order->items->first(), availableQuantity: 1, reason: 'Customer changed quantity', suppressAdminNotifications: true);
        $this->assertSame(6, $original->fresh()->inventory_quantity);
        $this->assertSame(3, $large->fresh()->inventory_quantity);
    }

    public function test_retiring_the_original_option_selects_a_new_base_without_changing_defaults_or_saved_packaging(): void
    {
        $this->admin();
        StoreShippingMethod::query()->update(['pricing_type' => 'cart_value']);
        $product = Product::factory()->create(['price' => 4.95, 'inventory_units' => 2, 'inventory_quantity' => 8, 'length_mm' => 100]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => '10-pack', 'price' => 14.95, 'inventory_units' => 10, 'inventory_quantity' => 3, 'length_mm' => 200]);
        $data = $this->payload($product);
        foreach ($data['variants'] as &$row) {
            $row['is_active'] = $row['id'] !== $original->id;
            $row['is_base'] = false;
            unset($row['length_mm'], $row['width_mm'], $row['height_mm'], $row['weight_grams']);
            $row['product_details'] = [['key' => 'Pack size', 'value' => $row['id'] === $original->id ? '2' : '10']];
        }
        unset($row);

        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors()->assertRedirect();

        $this->assertSame($large->id, $product->fresh()->default_variant_id);
        $this->assertSame('4.95', $product->fresh()->price);
        $this->assertSame(2, $product->fresh()->inventory_units);
        $this->assertFalse($original->fresh()->is_active);
        $this->assertSame(8, $original->fresh()->inventory_quantity);
        $this->assertSame([['key' => 'Pack size', 'value' => '2']], $original->fresh()->product_details);
        $this->assertSame(100, $product->fresh()->length_mm);
        $this->assertSame(200, $large->fresh()->length_mm);
        $this->assertSame('$14.95', $product->fresh()->priceRangeLabel());
        $this->get(route('shop.product.show', $product))->assertOk()->assertSee("selectedVariantId: '".$large->id."'", false);
        $this->postJson(route('shop.cart.add', $product), ['quantity' => 1])->assertOk();
        $line = app(StoreCartService::class)->lines()->first();
        $this->assertSame($large->id, $line->variant->id);
        $this->assertSame(14.95, $line->unit_price);
    }

    public function test_an_old_cart_stays_tied_to_the_original_option_after_the_default_changes(): void
    {
        $product = Product::factory()->create(['inventory_quantity' => 5]);
        app(StoreCartService::class)->add($product);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_quantity' => 5]);
        $product->update(['default_variant_id' => $large->id]);
        $this->assertSame($original->id, app(StoreCartService::class)->lines()->first()->variant->id);
        $original->update(['is_active' => false]);
        $this->assertCount(0, app(StoreCartService::class)->lines());
    }

    public function test_base_selection_and_active_product_rules_are_enforced_on_the_server(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        app(ProductVariantUpgrade::class)->convert($product);
        ProductVariant::factory()->create(['product_id' => $product->id]);
        $data = $this->payload($product);
        $data['variants'][0]['is_base'] = true;
        $data['variants'][1]['is_base'] = true;
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasErrors('variants');
        $data['variants'][1]['is_base'] = false;
        $data['variants'][0]['is_active'] = false;
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasErrors('variants.0.is_base');
        foreach ($data['variants'] as &$row) {
            $row['is_active'] = false;
            $row['is_base'] = false;
        }
        unset($row);
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasErrors('variants');
        $data['status'] = 'archived';
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertNull($product->fresh()->default_variant_id);
    }

    public function test_the_server_chooses_the_next_active_variant_when_the_current_base_is_retired(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        app(ProductVariantUpgrade::class)->convert($product);
        $middle = ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 10]);
        $next = ProductVariant::factory()->create(['product_id' => $product->id, 'sort_order' => 20]);
        $product->update(['default_variant_id' => $middle->id]);
        $data = $this->payload($product);
        foreach ($data['variants'] as &$row) {
            $row['is_base'] = false;
            $row['is_active'] = $row['id'] !== $middle->id;
        }
        unset($row);
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame($next->id, $product->fresh()->default_variant_id);
    }

    public function test_an_editor_opened_before_the_data_migration_saves_the_same_original_variant(): void
    {
        $this->admin();
        $product = Product::factory()->create(['inventory_quantity' => 4]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $data = $this->payload($product);
        $data['variants'][0]['id'] = null;
        $data['variants'][0]['inventory_quantity'] = 6;
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, $product->variants()->count());
        $this->assertSame(6, $original->fresh()->inventory_quantity);
    }

    public function test_saved_variant_ids_determine_the_original_option_when_restored_flags_are_stale(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $other = ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'OTHER-PACK']);
        $data = $this->payload($product);
        foreach ($data['variants'] as &$row) {
            $row['original_option'] = '1';
        }
        unset($row);

        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();

        $this->assertSame(2, $product->variants()->count());
        $this->assertSame($original->id, $product->fresh()->legacy_base_variant_id);
        $this->assertSame('OTHER-PACK', $other->fresh()->sku);
    }

    public function test_the_same_saved_variant_cannot_be_submitted_twice_even_with_different_skus(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        app(ProductVariantUpgrade::class)->convert($product);
        $data = $this->payload($product);
        $data['variants'][] = [...$data['variants'][0], 'name' => 'Another pack', 'sku' => 'DIFFERENT-CODE', 'is_base' => false, 'original_option' => false];

        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasErrors('variants');
        $this->assertSame(1, $product->variants()->count());
    }

    public function test_enabling_automatic_pricing_identifies_the_variant_that_needs_purchase_costs(): void
    {
        $this->admin();
        $product = Product::factory()->create(['unit_cost_ex_tax' => null, 'automatic_pricing' => false]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $original->update(['name' => '200 Pack', 'sku' => 'PACK-200', 'inventory_units' => 200]);
        $other = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => '50 Pack', 'sku' => 'PACK-50', 'inventory_units' => 50, 'is_active' => false]);
        $data = $this->payload($product);
        $data['variants'][0]['automatic_pricing'] = true;

        $this->put(route('admin.shop.product.update', $product), $data)
            ->assertSessionHasErrors(['variants.0.automatic_pricing' => 'Automatic pricing for "200 Pack" (PACK-200) needs purchase costs. Enter a purchase cost for this variant or a Default item cost, or tag a purchase expense to this product.']);
        $this->assertFalse($original->fresh()->automatic_pricing);

        $data['unit_cost_ex_tax'] = 10;
        $data['inventory_units'] = 200;
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertTrue($original->fresh()->automatic_pricing);
        $this->assertFalse($other->fresh()->automatic_pricing);
        $this->assertFalse($other->fresh()->is_active);
        $this->assertSame(2, $product->variants()->count());
        $this->assertSame(200, $product->fresh()->inventory_units);
        $this->assertSame(10.0, app(ProductRecipeService::class)->materialCost($product, $original));
        $this->assertSame(2.5, app(ProductRecipeService::class)->materialCost($product, $other));
    }

    public function test_new_products_use_the_same_variant_editor_and_can_be_duplicated(): void
    {
        $this->admin();
        $this->post(route('admin.shop.product.store'), [
            'variant_editor' => 1, 'title' => 'New item', 'sku' => 'NEW-ITEM', 'status' => 'active', 'product_type' => 'physical', 'price' => 9.95,
            'variants' => [['name' => 'Single', 'price' => 9.95, 'inventory_quantity' => 4, 'original_option' => true, 'is_base' => true, 'is_active' => true]],
        ])->assertSessionHasNoErrors();
        $product = Product::query()->where('sku', 'NEW-ITEM')->firstOrFail();
        $this->assertTrue($product->usesVariantRecords());
        $this->assertSame('NEW-ITEM', $product->defaultPurchasableVariant()->sku);
        $this->assertSame(4, $product->defaultPurchasableVariant()->inventory_quantity);
        $this->post(route('admin.shop.product.duplicate', $product))->assertRedirect();
        $copy = Product::query()->where('id', '!=', $product->id)->firstOrFail();
        $this->assertNotSame($product->legacy_base_variant_id, $copy->legacy_base_variant_id);
        $this->assertSame($copy->id, $copy->defaultPurchasableVariant()->product_id);
    }

    public function test_packaging_and_postage_fit_only_render_for_active_calculated_channels(): void
    {
        $this->admin();
        StoreShippingMethod::query()->update(['is_active' => false]);
        $product = Product::factory()->create();
        StoreShippingMethod::factory()->create(['code' => 'cart-total', 'pricing_type' => 'cart_value', 'is_active' => true]);
        $url = route('admin.shop.product.edit', $product);
        $this->get($url)->assertOk()->assertDontSeeText('Packaging overrides')->assertDontSeeText('Postage fit');
        $method = StoreShippingMethod::factory()->create(['code' => 'weight-volume', 'pricing_type' => 'calculated', 'calculator' => 'packages', 'is_active' => true]);
        $this->get($url)->assertOk()->assertSeeText('Packaging overrides')->assertSeeText('Postage fit');
        $method->update(['is_active' => false]);
        $this->get($url)->assertOk()->assertDontSeeText('Packaging overrides')->assertDontSeeText('Postage fit');
    }

    public function test_saved_variant_order_pins_the_base_first_and_matches_the_public_store(): void
    {
        $this->admin();
        $product = Product::factory()->create(['base_variant_name' => 'Retired pack']);
        $retired = app(ProductVariantUpgrade::class)->convert($product);
        $retired->update(['is_active' => false, 'sort_order' => 0]);
        $first = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Active First', 'sort_order' => 5]);
        $second = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Active Second', 'sort_order' => 10]);
        $base = ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Chosen Base', 'sort_order' => 99]);
        $product->update(['default_variant_id' => $base->id]);
        $this->assertSame([$base->id, $first->id, $second->id], $product->purchasableVariants()->pluck('id')->all());
        $this->assertSame([$base->id, $first->id, $second->id], $product->load('variants')->purchasableVariants()->pluck('id')->all());

        $this->put(route('admin.shop.product.update', $product), $this->payload($product))->assertSessionHasNoErrors();
        $this->assertSame([$base->id, $first->id, $second->id, $retired->id], $product->variants()->get()->pluck('id')->all());
        $this->assertSame([0, 1, 2, 3], $product->variants()->get()->pluck('sort_order')->all());

        $data = $this->payload($product);
        foreach ($data['variants'] as &$row) {
            $row['sort_order'] = $row['id'] === $second->id ? 1 : ($row['id'] === $first->id ? 2 : $row['sort_order']);
        }
        unset($row);
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame([$base->id, $second->id, $first->id], $product->fresh()->purchasableVariants()->pluck('id')->all());
        $this->get(route('shop.product.show', $product))->assertOk()
            ->assertSeeInOrder(['Chosen Base', 'Active Second', 'Active First'])
            ->assertDontSee('Retired pack');
    }

    public function test_variant_delete_controls_explain_why_history_must_be_retained(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $used = ProductVariant::factory()->create(['product_id' => $product->id]);
        $unused = ProductVariant::factory()->create(['product_id' => $product->id]);
        $item = StoreOrderItem::factory()->create(['product_id' => $product->id, 'product_variant_id' => $used->id]);
        $this->assertStringContainsString('original', $original->deletionDisabledReason($product));
        $this->assertStringContainsString('order or purchase history', $used->deletionDisabledReason($product));
        $this->assertNull($unused->deletionDisabledReason($product));
        $this->get(route('admin.shop.product.edit', $product))->assertOk()
            ->assertSee('This variant has order or purchase history. Deactivate it to retire it.');

        $data = $this->payload($product);
        $data['variants'] = array_values(array_filter($data['variants'], fn (array $row): bool => $row['id'] === $original->id));
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertFalse($used->fresh()->is_active);
        $this->assertSame($used->id, $item->fresh()->product_variant_id);
        $this->assertDatabaseMissing('product_variants', ['id' => $unused->id]);
    }

    public function test_changing_the_default_does_not_change_automatic_bulk_pricing_or_shared_stock(): void
    {
        $product = Product::factory()->create(['shared_inventory' => true, 'inventory_quantity' => 50, 'inventory_units' => 2, 'unit_cost_ex_tax' => 1, 'price' => 4.95]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'bulk_discount_percent' => 20]);
        $costing = app(ProductRecipeService::class);
        $before = $costing->suggestedPrice($product, $large);
        $product->update(['default_variant_id' => $large->id]);
        $costing->reset();
        $this->assertSame($before, $costing->suggestedPrice($product->fresh(), $large->fresh()));
        $original->update(['is_active' => false]);
        $this->assertSame(50, $product->fresh()->inventory_quantity);
        $this->assertNull($original->inventory_quantity);
        $this->assertSame(5, $product->fresh()->availableInventory($large));
    }
}
