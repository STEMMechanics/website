<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DocumentProductEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
    }

    public function test_both_editors_include_variant_prices_with_base_price_fallback_and_free_variants(): void
    {
        $product = Product::factory()->create(['price' => 11, 'inventory_quantity' => 10]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 22, 'is_active' => true]);
        $fallback = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => null, 'is_active' => true]);
        $free = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 0, 'is_active' => true]);
        foreach (['admin.quote.create', 'admin.invoice.create'] as $route) {
            $response = $this->get(route($route))->assertOk();
            $catalog = collect($response->viewData('catalogProducts'))->firstWhere('id', $product->id);
            $variants = collect($catalog['variants'])->keyBy('id');
            $this->assertSame(22.0, $variants[$variant->id]['price']);
            $this->assertSame(11.0, $variants[$fallback->id]['price']);
            $this->assertSame(0.0, $variants[$free->id]['price']);
            $response->assertSee('aria-label="Store product"', false)->assertSee('aria-label="Product variant"', false)
                ->assertSee('HRS / QTY')->assertSee('x-teleport="body"', false);
        }
        $this->get(route('admin.invoice.create'))->assertDontSee('Estimated cost centre allocation');
    }

    public function test_manual_invoice_saves_product_variant_and_price_and_reserves_inventory(): void
    {
        $customer = User::factory()->create();
        $product = Product::factory()->create(['price' => 11, 'inventory_quantity' => 10]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 22, 'inventory_quantity' => 8]);
        $line = [
            'kind' => 'product', 'description' => 'Selected store variant', 'quantity' => 2,
            'unit_price_inc_tax' => 22, 'gst_applicable' => true, 'tax_rate' => 0.1,
            'source_type' => Product::class, 'source_id' => $product->id,
            'details_json' => ['variant_id' => $variant->id, 'store_context' => ['product_id' => $product->id, 'variant_id' => $variant->id]],
        ];
        $this->post(route('admin.invoice.store'), [
            'invoice_number' => 'INV-PRODUCT-TEST', 'user_id' => $customer->id,
            'issue_date' => today()->toDateString(), 'line_items_json' => json_encode([$line]),
        ])->assertSessionHasNoErrors()->assertRedirect();
        $invoice = Invoice::query()->where('invoice_number', 'INV-PRODUCT-TEST')->firstOrFail();
        $saved = $invoice->lines()->firstOrFail();
        $this->assertSame(Product::class, $saved->source_type);
        $this->assertSame((string) $product->id, (string) $saved->source_id);
        $this->assertSame($variant->id, $saved->details_json['variant_id']);
        $this->assertSame(44.0, (float) $invoice->total_amount);
        $this->assertSame(10, $product->fresh()->inventory_quantity);
        $this->assertSame(6, $variant->fresh()->inventory_quantity);
        $variant->update(['is_active' => false, 'price' => 33]);
        $response = $this->get(route('admin.invoice.edit', $invoice))->assertOk();
        $catalog = collect($response->viewData('catalogProducts'))->firstWhere('id', $product->id);
        $this->assertTrue(collect($catalog['variants'])->contains('id', $variant->id));
        $this->assertSame(22.0, (float) $response->viewData('lineItemsSeed')[0]['unit_price_inc_tax']);
    }
}
