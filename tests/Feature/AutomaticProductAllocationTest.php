<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\ProductPurchase;
use App\Models\ProductVariant;
use App\Models\SiteOption;
use App\Models\Supplier;
use App\Models\User;
use App\Services\Finance\ProductAllocation;
use App\Services\Finance\ProductAllocationEditor;
use App\Services\Finance\ProductCostAllocation;
use App\Services\ProductRecipeService;
use App\Services\ProductVariantUpgrade;
use App\Support\RequestMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AutomaticProductAllocationTest extends TestCase
{
    use RefreshDatabase;

    private int $stock;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stock = DB::table('finance_categories')->insertGetId(['name' => 'Stock', 'kind' => 'cost', 'priority' => 15, 'active' => true]);
    }

    private function admin(): void
    {
        $user = User::factory()->create();
        $user->groups()->create(['slug' => 'admin']);
        $this->actingAs($user);
    }

    private function product(array $overrides = []): Product
    {
        return Product::factory()->create([
            'product_type' => 'physical', 'inventory_units' => 10, 'unit_cost_ex_tax' => 3.10,
            'operational_cost_ex_tax' => 6, 'time_cost_ex_tax' => 3.50,
            'price' => 15.95, 'tax_rate' => 0.1, 'markup_percent' => 30,
            'automatic_allocation' => true, 'automatic_pricing' => false, ...$overrides,
        ]);
    }

    private function rules(Product $product, ?ProductVariant $variant = null): ?array
    {
        app(ProductRecipeService::class)->reset();

        return app(ProductCostAllocation::class)->rules($product->fresh(), $variant);
    }

    private function purchase(Product $product, int $category = 2, string $date = '2026-10-01', ?ProductVariant $variant = null): Expense
    {
        $expense = Expense::factory()->create(['paid_on' => $date, 'total_amount' => 3.41, 'gst_amount' => .31]);
        DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => $category, 'cents' => 310]);
        ProductPurchase::query()->create(['expense_id' => $expense->id, 'product_id' => $product->id, 'product_variant_id' => $variant?->id,
            'quantity' => 1, 'total_cost_ex_tax' => 3.10, 'units_per_purchase' => 10]);

        return $expense;
    }

    private function payload(Product $product): array
    {
        return [...$product->only(['title', 'slug', 'sku', 'status', 'product_type', 'price', 'tax_rate', 'inventory_units', 'unit_cost_ex_tax', 'operational_cost_ex_tax', 'time_cost_ex_tax', 'markup_percent', 'automatic_pricing', 'automatic_allocation', 'purchase_cost_centre']),
            'variant_editor' => 1, 'allocation_remainder_json' => json_encode($product->allocation_remainder),
            'variants' => $product->variants()->get()->map(fn (ProductVariant $variant): array => [...$variant->toArray(),
                'is_base' => $variant->id === $product->default_variant_id, 'original_option' => $variant->id === $product->legacy_base_variant_id])->all()];
    }

    public function test_costs_default_to_stock_and_business_cash_is_a_complete_allocation(): void
    {
        $product = $this->product();
        $rules = $this->rules($product);
        $this->assertSame(['fixed' => [$this->stock => 310, 5 => 600, 6 => 350], 'percent' => []], $rules);
        $this->assertSame(1260, array_sum(app(ProductAllocation::class)->targets($rules, 1, 1450)));
        $this->assertNotContains($product->id, app(ProductAllocationEditor::class)->attentionIds());
        $product->update(['unit_cost_ex_tax' => null]);
        $this->assertNull($this->rules($product));
        $this->assertContains($product->id, app(ProductAllocationEditor::class)->attentionIds());
    }

    public function test_expense_evidence_supplier_defaults_mixed_receipts_and_explicit_overrides(): void
    {
        $product = $this->product();
        $expense = $this->purchase($product);
        $this->assertSame(310, $this->rules($product)['fixed'][2]);
        DB::table('finance_expense_splits')->where('expense_id', $expense->id)->update(['cents' => 155]);
        DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => $this->stock, 'cents' => 155]);
        $this->assertSame(310, $this->rules($product)['fixed'][$this->stock]);
        $product->update(['purchase_cost_centre' => 'consumables']);
        $this->assertSame(310, $this->rules($product)['fixed'][2]);
        $product->update(['purchase_cost_centre' => 'auto']);
        DB::table('finance_expense_splits')->where('expense_id', $expense->id)->delete();
        Supplier::findOrFail($expense->supplier_id)->update(['splits' => [2 => 100]]);
        $this->assertSame(310, $this->rules($product)['fixed'][2]);
    }

    public function test_classification_uses_the_purchase_that_supplies_this_options_cost(): void
    {
        $product = $this->product();
        $first = $this->purchase($product, 2);
        $this->purchase($product, $this->stock, '2026-10-02');
        $other = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 25]);
        $this->purchase($product, 2, '2026-10-03', $other);
        $this->assertSame(310, $this->rules($product)['fixed'][$this->stock]);
        $this->assertSame(775, $this->rules($product, $other)['fixed'][2]);
        $fallback = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 20, 'unit_cost_ex_tax' => null]);
        $this->assertSame(620, $this->rules($product, $fallback)['fixed'][$this->stock]);
        DB::table('product_purchases')->where('expense_id', $first->id)->update(['individual_units' => true]);
        $this->assertSame('consumables', app(ProductRecipeService::class)->purchaseCostCentres($product->fresh())['newVariant']);
    }

    public function test_kits_keep_component_classifications_and_scale_pack_sizes(): void
    {
        $stock = $this->product(['inventory_units' => 1, 'unit_cost_ex_tax' => .50]);
        $consumable = $this->product(['inventory_units' => 1, 'unit_cost_ex_tax' => .20, 'purchase_cost_centre' => 'consumables']);
        $kit = $this->product(['product_type' => 'kit', 'inventory_units' => 1]);
        $kit->recipeComponents()->create(['component_product_id' => $stock->id, 'quantity' => 2]);
        $kit->recipeComponents()->create(['component_product_id' => $consumable->id, 'quantity' => 3]);
        $this->assertSame(100, $this->rules($kit)['fixed'][$this->stock]);
        $this->assertSame(60, $this->rules($kit)['fixed'][2]);
        $large = ProductVariant::factory()->create(['product_id' => $kit->id, 'inventory_units' => 10]);
        $this->assertSame(1000, $this->rules($kit, $large)['fixed'][$this->stock]);
        $this->assertSame(600, $this->rules($kit, $large)['fixed'][2]);
        $kit->update(['purchase_cost_centre' => 'stock']);
        $this->assertSame(160, $this->rules($kit)['fixed'][$this->stock]);
        $this->assertArrayNotHasKey(2, $this->rules($kit)['fixed']);
    }

    public function test_shared_remainder_rules_and_product_overrides_conserve_cents(): void
    {
        $this->admin();
        $product = $this->product();
        $setting = SiteOption::query()->create(['name' => ProductCostAllocation::REMAINDER_OPTION, 'value' => '{}']);
        $this->putJson(route('admin.site_option.update', $setting), ['value' => json_encode([$this->stock => 4000, 2 => 6000])])->assertOk();
        $rules = $this->rules($product);
        $this->assertSame([2 => 6000, $this->stock => 4000], $rules['percent']);
        $targets = app(ProductAllocation::class)->targets($rules, 1, 1450);
        $this->assertSame(386, $targets[$this->stock]);
        $this->assertSame(114, $targets[2]);
        $this->assertSame(1450, array_sum($targets));
        $this->assertSame(2901, array_sum(app(ProductAllocation::class)->targets($rules, 2, 2901)));
        $product->update(['allocation_remainder' => []]);
        $this->assertSame([], $this->rules($product)['percent']);
        $product->update(['allocation_remainder' => [$this->stock => 5000]]);
        $this->assertSame(1355, array_sum(app(ProductAllocation::class)->targets($this->rules($product), 1, 1450)));
        $this->get(route('admin.site_option.edit', $setting))->assertOk()->assertSee('Split by percentage');
    }

    public function test_manual_allocations_survive_pricing_refreshes_and_mode_changes(): void
    {
        $this->admin();
        $product = $this->product(['automatic_allocation' => false, 'automatic_pricing' => true]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $manual = ['fixed' => [$this->stock => 500], 'percent' => [6 => 10000]];
        DB::table('finance_product_allocations')->insert(['scope' => 'product:'.$product->id.':'.$original->id,
            'product_id' => $product->id, 'variant_id' => $original->id, 'rules' => json_encode($manual)]);
        app(ProductRecipeService::class)->refreshPrices();
        $this->assertSame($manual, app(ProductAllocation::class)->resolve($product->id, $original->id)['rules']);
        $data = $this->payload($product->fresh());
        $data['automatic_allocation'] = 1;
        $data['purchase_cost_centre'] = 'consumables';
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame(310, app(ProductAllocation::class)->resolve($product->id, $original->id)['rules']['fixed'][2]);
        $data = $this->payload($product->fresh());
        $data['automatic_allocation'] = 0;
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame($manual, app(ProductAllocation::class)->resolve($product->id, $original->id)['rules']);
    }

    public function test_invoice_snapshots_keep_their_original_calculated_costs(): void
    {
        $product = $this->product();
        $invoice = Invoice::factory()->create();
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'product', 'source_type' => Product::class, 'source_id' => $product->id]);
        $this->assertSame(310, $line->product_allocation_snapshot['rules']['fixed'][$this->stock]);
        $product->update(['unit_cost_ex_tax' => 5, 'allocation_remainder' => [6 => 10000]]);
        app(ProductRecipeService::class)->reset();
        $this->assertSame(500, app(ProductAllocation::class)->resolve($product->id)['rules']['fixed'][$this->stock]);
        $this->assertSame(310, $line->fresh()->product_allocation_snapshot['rules']['fixed'][$this->stock]);
        $this->assertSame([], $line->fresh()->product_allocation_snapshot['rules']['percent']);
    }

    public function test_validation_rejects_invalid_categories_and_splits_without_partial_saves(): void
    {
        $this->admin();
        $product = $this->product();
        app(ProductVariantUpgrade::class)->convert($product);
        foreach ([['bad' => 10000], [2 => -1], [2 => 5000, 6 => 5001], [2 => 1.5], [2 => '10000'], [999999 => 10000]] as $invalid) {
            $data = $this->payload($product);
            $data['allocation_remainder_json'] = json_encode($invalid);
            $data['title'] = 'Invalid change';
            $this->putJson(route('admin.shop.product.update', $product), $data)->assertUnprocessable()->assertJsonValidationErrors('allocation_remainder_json');
            $this->assertNotSame('Invalid change', $product->fresh()->title);
            $this->postJson(route('admin.site_option.store'), ['name' => ProductCostAllocation::REMAINDER_OPTION, 'value' => json_encode($invalid)])->assertUnprocessable()->assertJsonValidationErrors('value');
        }
        $data = $this->payload($product);
        $data['purchase_cost_centre'] = 'Other';
        $this->putJson(route('admin.shop.product.update', $product), $data)->assertUnprocessable()->assertJsonValidationErrors('purchase_cost_centre');
    }

    public function test_hidden_retired_and_shared_manual_rules_are_not_rewritten(): void
    {
        $this->admin();
        $product = $this->product(['automatic_allocation' => false]);
        $retired = app(ProductVariantUpgrade::class)->convert($product);
        $retired->update(['is_active' => false]);
        $active = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 25]);
        $product->update(['default_variant_id' => $active->id]);
        foreach ([null, $retired->id] as $id) {
            DB::table('finance_product_allocations')->insert(['scope' => 'product:'.$product->id.':'.($id ?? 0), 'product_id' => $product->id, 'variant_id' => $id,
                'rules' => json_encode(['fixed' => [2 => 9000], 'percent' => [6 => 2500]])]);
        }
        $profileRules = ['fixed' => [$this->stock => 500], 'percent' => [6 => 10000]];
        $profile = DB::table('finance_product_profiles')->insertGetId(['name' => 'Keep my profile', 'rules' => json_encode($profileRules)]);
        DB::table('finance_product_allocations')->insert(['scope' => 'product:'.$product->id.':'.$active->id,
            'product_id' => $product->id, 'variant_id' => $active->id, 'profile_id' => $profile]);
        $before = DB::table('finance_product_allocations')->orderBy('scope')->get()->toJson();
        $data = $this->payload($product->fresh());
        $data['product_allocations_json'] = json_encode(['base' => ['fixed' => [], 'percent' => []], 'variants' => [
            ['inherit' => true, 'rules' => ['fixed' => [], 'percent' => []]], ['inherit' => false, 'rules' => $profileRules],
        ]]);
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->assertSame($before, DB::table('finance_product_allocations')->orderBy('scope')->get()->toJson());
    }

    public function test_missing_cost_centres_and_below_cost_active_packs_need_review_but_retired_packs_do_not(): void
    {
        $product = $this->product();
        $retired = app(ProductVariantUpgrade::class)->convert($product);
        $retired->update(['is_active' => false, 'price' => .95]);
        $active = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'price' => 15.95]);
        $product->update(['default_variant_id' => $active->id]);
        $this->assertNotContains($product->id, app(ProductAllocationEditor::class)->attentionIds());
        $active->update(['price' => .95]);
        $this->assertContains($product->id, app(ProductAllocationEditor::class)->attentionIds());
        $active->update(['price' => 0]);
        $this->assertContains($product->id, app(ProductAllocationEditor::class)->attentionIds());
        DB::table('finance_categories')->where('id', $this->stock)->update(['active' => false]);
        app(RequestMemo::class)->clear();
        $this->assertNull($this->rules($product));
    }

    public function test_an_unconfigured_manual_variant_can_still_be_saved_with_inherited_empty_rules(): void
    {
        $this->admin();
        $product = $this->product(['automatic_allocation' => false]);
        app(ProductVariantUpgrade::class)->convert($product);
        $allocation = app(ProductAllocationEditor::class)->data($product->fresh());
        $data = $this->payload($product->fresh());
        $data['product_allocations_json'] = json_encode(['base' => $allocation['base'], 'variants' => array_values($allocation['variants'])]);
        $this->put(route('admin.shop.product.update', $product), $data)->assertSessionHasNoErrors();
        $this->get(route('admin.shop.product.edit', $product))->assertOk()->assertSee('Automatic cost centre allocation')->assertSee('Purchase cost centre');
    }

    public function test_migration_round_trip_keeps_manual_rules_and_invoice_snapshots(): void
    {
        $product = $this->product();
        $line = InvoiceLine::factory()->create(['kind' => 'product', 'source_type' => Product::class, 'source_id' => $product->id]);
        DB::table('finance_product_allocations')->insert(['scope' => 'product:'.$product->id.':0', 'product_id' => $product->id,
            'rules' => json_encode(['fixed' => [2 => 500], 'percent' => []])]);
        $before = DB::table('finance_product_allocations')->get()->toJson();
        $migration = require database_path('migrations/2026_10_16_140000_add_automatic_product_allocations.php');
        $migration->down();
        $migration->up();
        $this->assertFalse($product->fresh()->automatic_allocation);
        $this->assertSame('auto', $product->fresh()->purchase_cost_centre);
        $this->assertNull($product->fresh()->allocation_remainder);
        $this->assertSame($before, DB::table('finance_product_allocations')->get()->toJson());
        $this->assertSame(310, $line->fresh()->product_allocation_snapshot['rules']['fixed'][$this->stock]);
    }

    public function test_old_null_selections_recover_the_original_pack_cost_instead_of_the_cost_basis_or_new_default(): void
    {
        $product = $this->product(['inventory_units' => 1, 'unit_cost_ex_tax' => .31]);
        $original = app(ProductVariantUpgrade::class)->convert($product);
        $original->update(['inventory_units' => 2, 'is_active' => false]);
        $active = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10]);
        $product->update(['default_variant_id' => $active->id]);
        $old = app(ProductAllocation::class)->resolve($product->id);
        $this->assertSame($original->id, $old['variant_id']);
        $this->assertSame(62, $old['rules']['fixed'][$this->stock]);
        $this->assertSame(310, app(ProductAllocation::class)->resolve($product->id, $active->id)['rules']['fixed'][$this->stock]);
    }
}
