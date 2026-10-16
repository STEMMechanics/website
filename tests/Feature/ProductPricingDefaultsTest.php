<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteOption;
use App\Models\User;
use App\Services\ProductRecipeService;
use App\Services\ProductVariantUpgrade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductPricingDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        $admin->groups()->create(['slug' => 'admin']);
        $this->actingAs($admin);
    }

    private function settingsPayload(array $defaults = []): array
    {
        return [
            'public_enabled' => 1,
            'max_satchel_weight_grams' => 5000,
            'boxed_shipping_label' => 'Manual quote',
            'boxed_shipping_message' => 'Manual shipping quote required.',
            'shipping_methods' => $this->get(route('admin.shop.settings.edit'))->assertOk()->viewData('shippingMethods'),
            ...$defaults,
        ];
    }

    private function product(array $overrides = []): Product
    {
        $product = Product::factory()->create([
            'product_type' => 'physical', 'inventory_units' => 1, 'unit_cost_ex_tax' => 2,
            'operational_cost_ex_tax' => null, 'time_cost_ex_tax' => null,
            'markup_percent' => 0, 'tax_rate' => 0.1, 'price' => 99,
            'automatic_pricing' => true, ...$overrides,
        ]);
        app(ProductVariantUpgrade::class)->convert($product);

        return $product;
    }

    public function test_settings_and_pricing_share_config_defaults_when_no_defaults_are_saved(): void
    {
        config()->set([
            'product-costing.item_operational_cost' => 0.75,
            'product-costing.kit_operational_cost' => 1.50,
            'product-costing.item_time_cost' => 4.25,
            'product-costing.markup_percent' => 35,
            'product-costing.bulk_discount_percent' => 15,
        ]);
        $this->get(route('admin.shop.settings.edit'))->assertOk()
            ->assertSeeText('Purchase costs and pricing defaults')
            ->assertSeeText('Markup (%)')
            ->assertSeeText('Bulk discount (%)')
            ->assertViewHas('itemOperationalCost', 0.75)
            ->assertViewHas('kitOperationalCost', 1.50)
            ->assertViewHas('itemTimeCost', 4.25)
            ->assertViewHas('markupPercent', 35.0)
            ->assertViewHas('bulkDiscountPercent', 15.0);
        $costing = app(ProductRecipeService::class);
        $this->assertSame(0.75, $costing->operationalCost(new Product(['product_type' => 'physical'])));
        $this->assertSame(8.50, $costing->timeCost(new Product(['product_type' => 'kit'])));
        $this->assertSame(35.0, $costing->defaultMarkup());
        $this->assertSame(15.0, $costing->defaultBulkDiscount());
        $this->get(route('admin.shop.product.create'))->assertOk()
            ->assertSeeText('defaults in Store Settings')
            ->assertSee(route('admin.shop.settings.edit').'#pricing-defaults', false);
    }

    public function test_saving_defaults_refreshes_inherited_automatic_prices_and_preserves_overrides(): void
    {
        $item = $this->product();
        $kit = $this->product(['product_type' => 'kit']);
        $override = $this->product(['operational_cost_ex_tax' => 1.50, 'time_cost_ex_tax' => 2.25]);
        $zero = $this->product(['operational_cost_ex_tax' => 0, 'time_cost_ex_tax' => 0]);
        $manual = $this->product(['automatic_pricing' => false]);
        $costing = app(ProductRecipeService::class);
        $costing->refreshPrices();
        $this->assertSame('6.95', $item->variants()->sole()->price);
        $this->assertSame('11.95', $kit->variants()->sole()->price);

        $this->put(route('admin.shop.settings.update'), $this->settingsPayload([
            'item_operational_cost_ex_tax' => '1.25',
            'kit_operational_cost_ex_tax' => '2.50',
            'item_time_cost_ex_tax' => '4.00',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        foreach (['item-operational' => '1.25', 'kit-operational' => '2.50', 'item-time' => '4.00'] as $name => $value) {
            $this->assertDatabaseHas('site_options', ['name' => 'store.costing.'.$name, 'value' => $value]);
        }
        foreach ([[$item, '8.95'], [$kit, '13.95'], [$override, '6.95'], [$zero, '2.95'], [$manual, '99.00']] as [$product, $price]) {
            $this->assertSame($price, $product->fresh()->price);
            $this->assertSame($price, $product->variants()->sole()->price);
        }
        $this->assertNull($item->fresh()->operational_cost_ex_tax);
        $this->assertNull($kit->fresh()->time_cost_ex_tax);
        $this->assertSame(1.25, $costing->operationalCost($item));
        $this->assertSame(8.0, $costing->timeCost($kit));
        $this->get(route('admin.shop.settings.edit'))->assertOk()
            ->assertViewHas('itemOperationalCost', 1.25)
            ->assertViewHas('kitOperationalCost', 2.50)
            ->assertViewHas('itemTimeCost', 4.0);
    }

    public function test_markup_and_discount_defaults_refresh_inherited_prices_and_preserve_overrides(): void
    {
        SiteOption::query()->create(['name' => 'store.costing.markup-percent', 'value' => '30.00']);
        SiteOption::query()->create(['name' => 'store.costing.bulk-discount-percent', 'value' => '20.00']);
        $item = $this->product(['markup_percent' => null]);
        $override = $this->product(['markup_percent' => 0, 'bulk_discount_percent' => 0]);
        $variantDefaults = [
            'inventory_units' => 5, 'unit_cost_ex_tax' => null, 'bulk_discount_percent' => null,
            'automatic_pricing' => true, 'price' => 99,
        ];
        $inherited = ProductVariant::factory()->create([...$variantDefaults, 'product_id' => $item->id]);
        $productOverride = ProductVariant::factory()->create([...$variantDefaults, 'product_id' => $override->id]);
        $variantOverride = ProductVariant::factory()->create([...$variantDefaults, 'product_id' => $item->id, 'bulk_discount_percent' => 0]);
        $manual = ProductVariant::factory()->create([...$variantDefaults, 'product_id' => $item->id, 'automatic_pricing' => false]);
        $costing = app(ProductRecipeService::class);
        $costing->refreshPrices();
        $this->assertSame('8.95', $item->fresh()->price);
        $this->assertSame('35.95', $inherited->fresh()->price);

        $this->put(route('admin.shop.settings.update'), $this->settingsPayload([
            'markup_percent' => '50.00', 'bulk_discount_percent' => '25.00',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('site_options', ['name' => 'store.costing.markup-percent', 'value' => '50.00']);
        $this->assertDatabaseHas('site_options', ['name' => 'store.costing.bulk-discount-percent', 'value' => '25.00']);
        $this->assertSame('9.95', $item->fresh()->price);
        $this->assertSame('37.95', $inherited->fresh()->price);
        $this->assertSame('33.95', $productOverride->fresh()->price);
        $this->assertSame('48.95', $variantOverride->fresh()->price);
        $this->assertSame('99.00', $manual->fresh()->price);
        $this->assertNull($item->fresh()->markup_percent);
        $this->assertNull($inherited->fresh()->bulk_discount_percent);
        $this->assertSame(0.0, $override->fresh()->markup_percent);
        $this->assertSame(0.0, $override->fresh()->bulk_discount_percent);
        $this->assertSame(50.0, $costing->defaultMarkup());
        $this->assertSame(25.0, $costing->defaultBulkDiscount());
        $this->get(route('admin.shop.settings.edit'))->assertOk()
            ->assertViewHas('markupPercent', 50.0)
            ->assertViewHas('bulkDiscountPercent', 25.0);
    }

    public function test_product_allowances_can_be_cleared_to_inherit_defaults_or_set_to_zero(): void
    {
        $product = $this->product(['operational_cost_ex_tax' => 2, 'time_cost_ex_tax' => 3]);

        foreach ([['', null, '6.95'], [0, 0.0, '2.95']] as [$input, $saved, $price]) {
            $this->put(route('admin.shop.product.update', $product), [
                ...$product->fresh()->only(['title', 'slug', 'sku', 'status', 'product_type', 'price', 'inventory_units', 'unit_cost_ex_tax', 'markup_percent', 'automatic_pricing']),
                'operational_cost_ex_tax' => $input,
                'time_cost_ex_tax' => $input,
                'variant_editor' => 1,
                'variants' => $product->variants()->get()->map(fn (ProductVariant $variant): array => [
                    ...$variant->toArray(), 'is_base' => $variant->id === $product->default_variant_id,
                    'original_option' => $variant->id === $product->legacy_base_variant_id,
                ])->all(),
            ])->assertRedirect()->assertSessionHasNoErrors();

            $this->assertSame($saved, $product->fresh()->operational_cost_ex_tax);
            $this->assertSame($saved, $product->fresh()->time_cost_ex_tax);
            $this->assertSame($price, $product->variants()->sole()->price);
        }
    }

    public function test_zero_defaults_are_saved_and_omitted_defaults_are_preserved(): void
    {
        $this->put(route('admin.shop.settings.update'), $this->settingsPayload([
            'item_operational_cost_ex_tax' => 0,
            'kit_operational_cost_ex_tax' => 0,
            'item_time_cost_ex_tax' => 0,
            'markup_percent' => 0,
            'bulk_discount_percent' => 0,
        ]))->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('admin.shop.settings.update'), $this->settingsPayload())
            ->assertRedirect()->assertSessionHasNoErrors();

        foreach (['item-operational', 'kit-operational', 'item-time', 'markup-percent', 'bulk-discount-percent'] as $name) {
            $this->assertDatabaseHas('site_options', ['name' => 'store.costing.'.$name, 'value' => '0.00']);
        }
        $costing = app(ProductRecipeService::class);
        $this->assertSame(0.0, $costing->defaultOperationalCost());
        $this->assertSame(0.0, $costing->defaultOperationalCost(true));
        $this->assertSame(0.0, $costing->defaultTimeCost(true));
        $this->assertSame(0.0, $costing->defaultMarkup());
        $this->assertSame(0.0, $costing->defaultBulkDiscount());
    }

    public function test_pricing_defaults_validate_their_ranges_and_decimal_precision(): void
    {
        $payload = $this->settingsPayload();
        foreach ([
            'item_operational_cost_ex_tax' => 10000,
            'kit_operational_cost_ex_tax' => 10000,
            'item_time_cost_ex_tax' => 10000,
            'markup_percent' => 10000,
            'bulk_discount_percent' => 100,
        ] as $field => $maximum) {
            foreach (['', 'invalid', -1, $maximum + 0.01, 1.001] as $value) {
                $this->putJson(route('admin.shop.settings.update'), [...$payload, $field => $value])
                    ->assertUnprocessable()->assertJsonValidationErrors($field);
            }
        }
        $this->assertSame(0, SiteOption::query()->where('name', 'like', 'store.costing.%')->count());
    }
}
