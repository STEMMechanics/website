<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteOption;
use App\Models\User;
use App\Services\ProductRecipeService;
use App\Services\ProductVariantUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BulkDiscountInheritanceTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);
        $this->actingAs($admin);
    }

    /** @return array{Product, ProductVariant} */
    private function packs(?float $discount = null): array
    {
        $product = Product::factory()->create([
            'product_type' => 'physical', 'inventory_units' => 10,
            'unit_cost_ex_tax' => 2.91, 'operational_cost_ex_tax' => 3,
            'time_cost_ex_tax' => 6.5, 'markup_percent' => 30,
            'tax_rate' => 0.1, 'price' => 17.95, 'automatic_pricing' => true,
            'bulk_discount_percent' => $discount,
        ]);
        app(ProductVariantUpgrade::class)->convert($product);
        $large = ProductVariant::factory()->create([
            'product_id' => $product->id, 'inventory_units' => 25,
            'automatic_pricing' => true, 'unit_cost_ex_tax' => null,
            'bulk_discount_percent' => null, 'price' => 99,
        ]);

        return [$product, $large];
    }

    private function payload(Product $product): array
    {
        return [
            ...$product->only(['title', 'slug', 'sku', 'status', 'product_type', 'price', 'inventory_units', 'unit_cost_ex_tax', 'operational_cost_ex_tax', 'time_cost_ex_tax', 'markup_percent', 'automatic_pricing', 'tax_rate', 'bulk_discount_percent']),
            'variant_editor' => 1,
            'variants' => $product->variants()->get()->map(fn (ProductVariant $variant): array => [
                ...$variant->toArray(), 'is_base' => $variant->id === $product->default_variant_id,
                'original_option' => $variant->id === $product->legacy_base_variant_id,
            ])->all(),
        ];
    }

    public function test_discounts_resolve_through_all_three_levels_and_keep_zero_as_an_override(): void
    {
        [$product, $large] = $this->packs();
        $pricing = app(ProductRecipeService::class);
        $this->assertSame(20.0, $pricing->defaultBulkDiscount());
        $this->assertSame(35.95, $pricing->suggestedPrice($product, $large));
        $product->update(['bulk_discount_percent' => 10]);
        $this->assertSame(40.95, $pricing->suggestedPrice($product, $large));
        $large->update(['bulk_discount_percent' => 5]);
        $this->assertSame(42.95, $pricing->suggestedPrice($product, $large));
        $large->update(['bulk_discount_percent' => 0]);
        $this->assertSame(44.95, $pricing->suggestedPrice($product, $large));
        $large->update(['bulk_discount_percent' => null]);
        $product->update(['bulk_discount_percent' => 0]);
        $this->assertSame(44.95, $pricing->suggestedPrice($product, $large));
        $product->update(['bulk_discount_percent' => null]);
        $this->assertSame(35.95, $pricing->suggestedPrice($product, $large));
    }

    public function test_product_and_variant_overrides_can_be_saved_cleared_and_set_to_zero(): void
    {
        $this->admin();
        [$product, $large] = $this->packs();
        foreach ([[10, '', '40.95'], [5, 0, '44.95'], ['', '', '35.95'], [0, '', '44.95']] as [$productDiscount, $variantDiscount, $price]) {
            $data = $this->payload($product->fresh());
            $data['bulk_discount_percent'] = $productDiscount;
            foreach ($data['variants'] as &$row) {
                if ($row['id'] === $large->id) {
                    $row['bulk_discount_percent'] = $variantDiscount;
                }
            }
            unset($row);
            $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
            $this->assertSame($productDiscount === '' ? null : (float) $productDiscount, $product->fresh()->bulk_discount_percent);
            $this->assertSame($variantDiscount === '' ? null : '0.00', $large->fresh()->bulk_discount_percent);
            $this->assertSame($price, $large->fresh()->price);
        }
        $this->get(route('admin.shop.product.edit', $product))->assertOk()->assertSee('Blank inherits the product discount; 0 means no discount.');
    }

    public function test_older_product_forms_preserve_an_existing_product_override_when_the_field_is_absent(): void
    {
        $this->admin();
        [$product] = $this->packs(12.5);
        $data = $this->payload($product);
        unset($data['bulk_discount_percent']);
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame(12.5, $product->fresh()->bulk_discount_percent);
    }

    public function test_changing_the_site_default_refreshes_only_inherited_automatic_prices(): void
    {
        $this->admin();
        [$product, $inherited] = $this->packs();
        [, $productOverride] = $this->packs(10);
        $variantOverride = ProductVariant::factory()->create([
            'product_id' => $product->id, 'inventory_units' => 25,
            'automatic_pricing' => true, 'bulk_discount_percent' => 0, 'unit_cost_ex_tax' => null,
        ]);
        $manual = ProductVariant::factory()->create([
            'product_id' => $product->id, 'inventory_units' => 25,
            'automatic_pricing' => false, 'bulk_discount_percent' => null, 'price' => 99,
        ]);
        app(ProductRecipeService::class)->refreshPrices();
        $this->assertSame('35.95', $inherited->fresh()->price);
        $this->postJson(route('admin.site_option.store'), ['name' => 'store.costing.bulk-discount-percent', 'value' => '25'])->assertOk();
        $this->assertSame('33.95', $inherited->fresh()->price);
        $this->assertSame('40.95', $productOverride->fresh()->price);
        $this->assertSame('44.95', $variantOverride->fresh()->price);
        $this->assertSame('99.00', $manual->fresh()->price);
        $setting = SiteOption::query()->where('name', 'store.costing.bulk-discount-percent')->firstOrFail();
        $this->putJson(route('admin.site_option.update', $setting), ['value' => '0'])->assertOk();
        $this->assertSame(0.0, app(ProductRecipeService::class)->defaultBulkDiscount());
        $this->assertSame('44.95', $inherited->fresh()->price);
        $this->assertSame('40.95', $productOverride->fresh()->price);
    }

    public function test_discount_ranges_are_validated_at_each_level(): void
    {
        $this->admin();
        [$product, $large] = $this->packs();
        foreach ([-1, 100.01, 12.345] as $invalid) {
            $this->postJson(route('admin.site_option.store'), ['name' => 'store.costing.bulk-discount-percent', 'value' => (string) $invalid])->assertUnprocessable()->assertJsonValidationErrors('value');
            $data = $this->payload($product);
            $data['bulk_discount_percent'] = $invalid;
            $this->putJson(route('admin.shop.product.update', $product), $data)->assertUnprocessable()->assertJsonValidationErrors('bulk_discount_percent');
            $data['bulk_discount_percent'] = null;
            $data['variants'][1]['bulk_discount_percent'] = $invalid;
            $this->putJson(route('admin.shop.product.update', $product), $data)->assertUnprocessable()->assertJsonValidationErrors('variants.1.bulk_discount_percent');
        }
        $setting = SiteOption::query()->create(['name' => 'store.costing.bulk-discount-percent', 'value' => '20']);
        $this->putJson(route('admin.site_option.update', $setting), ['value' => '101'])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson(route('admin.site_option.update', $setting), ['value' => '100'])->assertOk();
        $this->assertSame('24.95', $large->fresh()->price);
    }

    public function test_migration_preserves_existing_rates_and_rollback_materializes_inherited_rates(): void
    {
        SiteOption::query()->create(['name' => 'store.costing.bulk-discount-percent', 'value' => '17']);
        [$siteProduct, $siteVariant] = $this->packs();
        [$zeroProduct, $zeroVariant] = $this->packs(0);
        [$product, $variant] = $this->packs(5);
        $explicit = ProductVariant::factory()->create(['product_id' => $product->id, 'bulk_discount_percent' => 20]);
        $migration = require database_path('migrations/2026_10_16_130000_add_bulk_discount_inheritance.php');
        $migration->down();
        foreach ([[$siteVariant, 17], [$zeroVariant, 0], [$variant, 5], [$explicit, 20]] as [$option, $discount]) {
            $this->assertSame((float) $discount, (float) DB::table('product_variants')->where('id', $option->id)->value('bulk_discount_percent'));
        }
        $migration->up();
        foreach ([$siteProduct, $zeroProduct, $product] as $item) {
            $this->assertNull($item->fresh()->bulk_discount_percent);
            $this->assertSame('17.95', $item->fresh()->price);
        }
        foreach ([[$siteVariant, '17.00'], [$zeroVariant, '0.00'], [$variant, '5.00'], [$explicit, '20.00']] as [$option, $discount]) {
            $this->assertSame($discount, $option->fresh()->bulk_discount_percent);
        }
        $new = ProductVariant::factory()->create(['product_id' => $product->id]);
        $this->assertNull($new->fresh()->bulk_discount_percent);
    }
}
