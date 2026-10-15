<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ProductRecipeService;
use App\Services\ProductVariantUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPackPricingTest extends TestCase
{
    use RefreshDatabase;

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create([
            'product_type' => 'physical', 'inventory_units' => 1,
            'unit_cost_ex_tax' => 0.291, 'operational_cost_ex_tax' => 3,
            'time_cost_ex_tax' => 6.5, 'markup_percent' => 20,
            'tax_rate' => 0.1, 'price' => 12.95, 'automatic_pricing' => true,
            ...$overrides,
        ]);
    }

    public function test_pack_pricing_charges_handling_once_without_scaling_single_item_rounding(): void
    {
        $product = $this->product();
        $small = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 2, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 0]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 0]);
        $pricing = app(ProductRecipeService::class);

        $this->assertSame(12.95, $pricing->suggestedPrice($product));
        $this->assertSame(13.95, $pricing->suggestedPrice($product, $small));
        $this->assertSame(16.95, $pricing->suggestedPrice($product, $large));
        $this->assertSame(2.91, $pricing->materialCost($product, $large));
        $this->assertFalse($pricing->pricingDetails($product, $large)['minimum_applied']);

        $large->update(['bulk_discount_percent' => 20]);
        $details = $pricing->pricingDetails($product, $large);
        $this->assertSame(15.95, $details['bulk_price']);
        $this->assertSame(16.95, $details['price']);
        $this->assertTrue($details['minimum_applied']);
    }

    public function test_the_cost_basis_quantity_and_fallback_price_do_not_inflate_automatic_pack_prices(): void
    {
        $product = $this->product(['price' => 999]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 0]);
        $pricing = app(ProductRecipeService::class);
        $this->assertSame(16.95, $pricing->suggestedPrice($product, $large));
        $product->update(['inventory_units' => 100, 'unit_cost_ex_tax' => 29.1]);
        $pricing->reset();
        $this->assertSame(16.95, $pricing->suggestedPrice($product, $large));
    }

    public function test_a_manual_fallback_scales_only_the_item_portion_and_preserves_the_cost_minimum(): void
    {
        $product = $this->product(['automatic_pricing' => false, 'price' => 14.95]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 20]);
        $pricing = app(ProductRecipeService::class);
        $this->assertSame(31.95, $pricing->suggestedPrice($product, $large));
        $large->update(['bulk_discount_percent' => 100]);
        $this->assertSame(16.95, $pricing->suggestedPrice($product, $large));
    }

    public function test_an_independently_costed_pack_can_be_priced_when_the_shared_cost_is_unknown(): void
    {
        $product = $this->product(['unit_cost_ex_tax' => null]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'unit_cost_ex_tax' => 2.91]);
        $this->assertSame(16.95, app(ProductRecipeService::class)->suggestedPrice($product, $large));
    }

    public function test_automatic_prices_refresh_to_pack_costs_and_preserve_the_last_price_when_costs_disappear(): void
    {
        $product = $this->product();
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 0, 'automatic_pricing' => true, 'price' => 129.95]);
        $pricing = app(ProductRecipeService::class);
        $pricing->refreshPrices();
        $this->assertSame('16.95', $large->fresh()->price);
        $product->update(['unit_cost_ex_tax' => null]);
        $pricing->refreshPrices();
        $this->assertSame('16.95', $large->fresh()->price);
    }

    /** @return array{Product, ProductVariant, ProductVariant} */
    private function sellingPacks(): array
    {
        $product = $this->product(['markup_percent' => 30]);
        $retired = app(ProductVariantUpgrade::class)->convert($product);
        $retired->update(['inventory_units' => 2, 'is_active' => false, 'unit_cost_ex_tax' => null, 'automatic_pricing' => true]);
        $minimum = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 0, 'automatic_pricing' => true, 'price' => 129.95]);
        $large = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 25, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => 0, 'automatic_pricing' => true, 'price' => 24.95]);
        $product->update(['default_variant_id' => $minimum->id]);

        return [$product, $minimum, $large];
    }

    public function test_larger_selling_packs_follow_the_minimum_pack_with_only_the_chosen_discount(): void
    {
        [$product, $minimum, $large] = $this->sellingPacks();
        $pricing = app(ProductRecipeService::class);
        $this->assertSame(17.95, $pricing->suggestedPrice($product, $minimum));
        $this->assertSame(44.95, $pricing->suggestedPrice($product, $large));
        $this->assertSame(24.95, $pricing->pricingDetails($product, $large)['minimum_price']);
        $large->update(['bulk_discount_percent' => 20]);
        $this->assertSame(35.95, $pricing->suggestedPrice($product, $large));
        $large->update(['bulk_discount_percent' => 100]);
        $this->assertSame(24.95, $pricing->suggestedPrice($product, $large));
        $this->assertTrue($pricing->pricingDetails($product, $large)['minimum_applied']);
    }

    public function test_automatic_minimum_pack_pricing_is_stable_across_refreshes_and_default_changes(): void
    {
        [$product, $minimum, $large] = $this->sellingPacks();
        $pricing = app(ProductRecipeService::class);
        $pricing->refreshPrices();
        $this->assertSame('17.95', $minimum->fresh()->price);
        $this->assertSame('44.95', $large->fresh()->price);
        $product->update(['default_variant_id' => $large->id, 'price' => 999, 'inventory_units' => 100, 'unit_cost_ex_tax' => 29.1]);
        $pricing->refreshPrices();
        $pricing->refreshPrices();
        $this->assertSame('17.95', $minimum->fresh()->price);
        $this->assertSame('44.95', $large->fresh()->price);
    }

    public function test_manual_minimum_pack_prices_and_retirement_change_the_bulk_price_basis(): void
    {
        [$product, $minimum, $large] = $this->sellingPacks();
        $minimum->update(['automatic_pricing' => false, 'price' => 19.95]);
        $large->update(['bulk_discount_percent' => 20]);
        $pricing = app(ProductRecipeService::class);
        $this->assertSame(39.95, $pricing->suggestedPrice($product->fresh(), $large));
        $minimum->update(['is_active' => false]);
        $pricing->reset();
        $this->assertSame(24.95, $pricing->suggestedPrice($product->fresh(), $large));
    }

    public function test_an_unknown_automatic_minimum_pack_cost_preserves_existing_bulk_prices(): void
    {
        [$product, $minimum, $large] = $this->sellingPacks();
        $product->update(['unit_cost_ex_tax' => null]);
        $large->update(['unit_cost_ex_tax' => 7.275]);
        $pricing = app(ProductRecipeService::class);
        $this->assertNull($pricing->suggestedPrice($product->fresh(), $large));
        $pricing->refreshPrices();
        $this->assertSame('129.95', $minimum->fresh()->price);
        $this->assertSame('24.95', $large->fresh()->price);
    }
}
