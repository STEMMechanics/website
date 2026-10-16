<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrderItem;
use App\Models\StoreShippingMethod;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ProductVariantUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductVariantCopyTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::query()->create(['user_id' => (string) $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    private function copyPayload(Product $product, string $sku): array
    {
        $original = $product->variants()->sole();

        return [
            ...$product->only(['title', 'slug', 'sku', 'status', 'product_type', 'price', 'shared_inventory']),
            'variant_editor' => 1,
            'variants' => [
                [...$original->toArray(), 'original_option' => true, 'is_base' => true],
                [...$original->toArray(), 'id' => null, 'original_option' => false, 'is_base' => false,
                    'name' => $original->name.' - COPY', 'sku' => $sku, 'inventory_quantity' => 0, 'sort_order' => 1],
            ],
        ];
    }

    public function test_copy_skus_avoid_saved_products_variants_and_unsaved_skus_without_creating_records(): void
    {
        $this->admin();
        $product = Product::factory()->create(['sku' => 'AAA-10']);
        Product::factory()->create(['sku' => 'aaa-10-copy']);
        ProductVariant::factory()->create(['product_id' => $product->id, 'sku' => 'aaa-10-copy-2', 'is_active' => false]);

        $this->postJson(route('admin.shop.product.variant-copy-sku'), [
            'sku' => 'AAA-10', 'reserved_skus' => ['AAA-10-COPY-3', 'aaa-10-copy-4'],
        ])->assertOk()->assertExactJson(['sku' => 'AAA-10-COPY-5']);

        $this->assertDatabaseCount('products', 2);
        $this->assertDatabaseCount('product_variants', 1);
        $this->assertSame('AAA-10', $product->fresh()->sku);
    }

    public function test_copy_skus_keep_the_suffix_within_the_field_limit_and_support_blank_skus(): void
    {
        $this->admin();
        $source = str_repeat('é', 120);
        $first = mb_substr($source, 0, 115).'-COPY';
        $this->postJson(route('admin.shop.product.variant-copy-sku'), ['sku' => $source])
            ->assertOk()->assertExactJson(['sku' => $first]);
        $second = $this->postJson(route('admin.shop.product.variant-copy-sku'), [
            'sku' => $source, 'reserved_skus' => [$first],
        ])->assertOk()->json('sku');
        $this->assertSame(120, mb_strlen($second));
        $this->assertStringEndsWith('-COPY-2', $second);

        $this->postJson(route('admin.shop.product.variant-copy-sku'), ['reserved_skus' => [null, '', 'variant-COPY']])
            ->assertOk()->assertExactJson(['sku' => 'variant-COPY-2']);
    }

    public function test_only_admins_can_check_copy_skus_and_inputs_are_validated(): void
    {
        $this->actingAs(User::factory()->create())
            ->postJson(route('admin.shop.product.variant-copy-sku'), ['sku' => 'AAA'])
            ->assertForbidden();
        $this->admin();
        $this->postJson(route('admin.shop.product.variant-copy-sku'), [
            'sku' => str_repeat('A', 121), 'reserved_skus' => [42],
        ])->assertUnprocessable()->assertJsonValidationErrors(['sku', 'reserved_skus.0']);
    }

    public function test_saving_a_copy_keeps_its_settings_and_the_existing_base_stock_and_history(): void
    {
        $this->admin();
        StoreShippingMethod::query()->update(['is_active' => true, 'pricing_type' => 'calculated']);
        $product = Product::factory()->create(['sku' => 'AAA-10', 'base_variant_name' => '10 Pack', 'inventory_quantity' => 7]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $original->update([
            'description' => 'Variant notes', 'product_details' => [['key' => 'Colour', 'value' => 'Black']],
            'price' => 19.95, 'compare_at_price' => 24.95, 'inventory_units' => 10, 'unit_cost_ex_tax' => 0.25,
            'bulk_discount_percent' => 15, 'low_stock_threshold' => 2,
            'allow_backorder' => true, 'backorder_shipping_estimate_type' => 'dynamic', 'backorder_shipping_offset_days' => 7,
            'weight_grams' => 120, 'length_mm' => 100, 'width_mm' => 50, 'height_mm' => 20,
        ]);
        $orderItem = StoreOrderItem::factory()->create(['product_id' => $product->id, 'product_variant_id' => $original->id]);
        $sku = $this->postJson(route('admin.shop.product.variant-copy-sku'), ['sku' => $original->sku])
            ->assertOk()->json('sku');

        $this->put(route('admin.shop.product.update', $product), $this->copyPayload($product, $sku))
            ->assertSessionHasNoErrors()->assertRedirect();

        $copy = $product->variants()->where('sku', $sku)->sole();
        $this->assertNotSame($original->id, $copy->id);
        $this->assertSame('10 Pack - COPY', $copy->name);
        $this->assertSame(0, $copy->inventory_quantity);
        $this->assertSame(7, $original->fresh()->inventory_quantity);
        $this->assertSame($original->id, $product->fresh()->default_variant_id);
        $this->assertSame($original->id, $product->fresh()->legacy_base_variant_id);
        $this->assertSame($original->id, $orderItem->fresh()->product_variant_id);
        foreach (['description', 'product_details', 'price', 'compare_at_price', 'inventory_units', 'unit_cost_ex_tax',
            'bulk_discount_percent', 'automatic_pricing', 'low_stock_threshold', 'allow_backorder',
            'backorder_shipping_estimate_type', 'backorder_shipping_offset_days',
            'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'is_active'] as $field) {
            $this->assertSame($original->fresh()->$field, $copy->$field, $field);
        }
    }

    public function test_a_sku_taken_after_the_copy_check_is_still_rejected_on_save(): void
    {
        $this->admin();
        $product = Product::factory()->create(['sku' => 'AAA-10']);
        app(ProductVariantUpgrade::class)->convert($product);
        $sku = $this->postJson(route('admin.shop.product.variant-copy-sku'), ['sku' => $product->sku])
            ->assertOk()->json('sku');
        ProductVariant::factory()->create(['sku' => $sku]);

        $this->put(route('admin.shop.product.update', $product), $this->copyPayload($product, $sku))
            ->assertSessionHasErrors('variants.1.sku');

        $this->assertSame(1, $product->variants()->count());
    }
}
