<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\StoreOrder;
use App\Models\TaxAdjustment;
use App\Models\TaxAdjustmentLine;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\ProductAllocation;
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ProductAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    private function config(Product $product, array $rules, ?ProductVariant $variant = null): void
    {
        DB::table('finance_product_allocations')->updateOrInsert(['scope' => app(ProductAllocation::class)->scope($product->id, $variant?->id)], ['product_id' => $product->id, 'variant_id' => $variant?->id, 'updated_by' => auth()->id(), 'rules' => json_encode($rules)]);
    }

    public function test_profiles_variants_and_snapshot_rules_do_not_rewrite_existing_invoices(): void
    {
        $user = $this->admin();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $this->post(route('admin.product-allocation.profile.save'), ['name' => 'Tape and assembly', 'fixed' => [2 => '2.50'], 'percent' => [6 => '50']])->assertSessionHasNoErrors();
        $profile = DB::table('finance_product_profiles')->first();
        $this->post(route('admin.product-allocation.save', $product), ['mode' => 'profile', 'profile_id' => $profile->id])->assertSessionHasNoErrors();
        $this->assertSame(250, app(ProductAllocation::class)->resolve($product->id, $variant->id)['rules']['fixed'][2]);
        $this->post(route('admin.product-allocation.save', $product), ['variant_id' => $variant->id, 'mode' => 'custom', 'fixed' => [2 => '5'], 'percent' => [6 => '100']])->assertSessionHasNoErrors();
        $invoice = Invoice::factory()->create(['created_by' => $user->id, 'total_amount' => 22, 'gst_amount' => 2]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'product', 'source_type' => Product::class, 'source_id' => $product->id, 'quantity' => 2, 'details_json' => ['variant_id' => $variant->id], 'line_total_ex_tax' => 20]);
        $this->assertSame(500, $line->product_allocation_snapshot['rules']['fixed'][2]);
        $this->assertArrayNotHasKey('product_allocation_snapshot', $line->toArray());
        $this->config($product, ['fixed' => [2 => 900], 'percent' => [6 => 10000]], $variant);
        $this->assertSame([2 => 1000, 6 => 1000], app(ProductAllocation::class)->lines($invoice)[$invoice->id.':'.$line->line_number]['targets']);
        $this->get(route('admin.product-allocation.edit', ['product' => $product, 'variant' => $variant->id]))->assertOk()->assertSee('Use base product allocation');
        $this->get(route('admin.product-allocation.profiles'))->assertOk()->assertSee('Tape and assembly');
        $this->post(route('admin.product-allocation.save', $product), ['mode' => 'custom', 'percent' => [1 => '80', 2 => '30']])->assertSessionHasErrors('percent');
        $otherVariant = ProductVariant::factory()->create();
        $this->post(route('admin.product-allocation.save', $product), ['mode' => 'inherit', 'variant_id' => $otherVariant->id])->assertSessionHasErrors('variant_id');
    }

    public function test_invoice_edits_preserve_saved_rules_until_defaults_are_explicitly_reapplied(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        $this->config($product, ['fixed' => [2 => 250], 'percent' => [6 => 10000]]);
        $item = ['kind' => 'product', 'description' => 'Tape kit', 'source_type' => Product::class, 'source_id' => $product->id, 'quantity' => 1, 'unit_price' => 10, 'gst_applicable' => true];
        $payload = ['invoice_number' => 'PRODUCT-EDIT', 'issue_date' => today()->toDateString(), 'line_items_json' => json_encode([$item])];
        $this->post(route('admin.invoice.store'), $payload)->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'PRODUCT-EDIT')->firstOrFail();
        $old = $invoice->lines()->firstOrFail();
        $this->assertSame(250, $old->product_allocation_snapshot['rules']['fixed'][2]);
        $this->config($product, ['fixed' => [2 => 500], 'percent' => [6 => 10000]]);
        $item['id'] = $old->id;
        $item['quantity'] = 2;
        $item['product_allocation_snapshot'] = ['rules' => ['fixed' => [2 => 9999]]];
        $payload['line_items_json'] = json_encode([['kind' => 'generic', 'description' => 'Other item', 'quantity' => 1, 'unit_price' => 1], $item]);
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $saved = $invoice->lines()->where('kind', 'product')->firstOrFail();
        $this->assertSame(2, $saved->line_number);
        $this->assertSame(250, $saved->product_allocation_snapshot['rules']['fixed'][2]);
        $this->post(route('admin.product-allocation.apply', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(500, $saved->fresh()->product_allocation_snapshot['rules']['fixed'][2]);
        $this->assertSame(1000, app(InvoiceAllocation::class)->context($invoice->fresh())['targets'][2]);
    }

    public function test_quote_conversion_snapshots_the_variant_allocation(): void
    {
        $this->admin();
        $product = Product::factory()->create();
        $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
        $this->config($product, ['fixed' => [2 => 250], 'percent' => []]);
        $this->config($product, ['fixed' => [2 => 750], 'percent' => []], $variant);
        $quote = Quote::factory()->create(['line_items' => [['kind' => 'product', 'source_id' => $product->id, 'source_variant_id' => $variant->id, 'description' => 'Kit', 'quantity' => 2, 'unit_price' => 20, 'gst_applicable' => true]]]);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $line = $invoice->lines()->firstOrFail();
        $this->assertSame(750, $line->product_allocation_snapshot['rules']['fixed'][2]);
        $this->assertSame($variant->id, $line->product_allocation_snapshot['variant_id']);
    }

    public function test_store_checkout_creates_a_budget_from_the_selected_variant(): void
    {
        Queue::fake();
        $admin = $this->admin();
        $admin->update(['account_terms_days' => 14]);
        $product = Product::factory()->create(['price' => 22, 'product_type' => Product::PRODUCT_TYPE_DIGITAL]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 44]);
        $this->config($product, ['fixed' => [2 => 500], 'percent' => [6 => 10000]], $variant);
        $this->post(route('shop.cart.add', $product), ['quantity' => 2, 'product_variant_id' => $variant->id])->assertSessionHasNoErrors();
        $this->post(route('shop.checkout.place-order'), ['payment_method' => 'account_terms', 'billing_name' => 'Allocation buyer', 'billing_email' => 'allocation-buyer@example.test', 'billing_phone' => '0400123456', 'billing_address' => '12 Invoice Street', 'billing_city' => 'Brisbane', 'billing_state' => 'QLD', 'billing_postcode' => '4000', 'billing_country' => 'Australia'])->assertSessionHasNoErrors();
        $invoice = StoreOrder::latest('id')->firstOrFail()->invoice;
        $line = $invoice->lines()->where('kind', 'product')->firstOrFail();
        $this->assertSame($variant->id, $line->product_allocation_snapshot['variant_id']);
        $context = app(InvoiceAllocation::class)->context($invoice);
        $this->assertNotNull($context['budget']);
        $this->assertSame(1000, $context['targets'][2]);
    }

    public function test_cost_recovery_is_capped_and_margin_percentages_conserve_cents(): void
    {
        $service = app(ProductAllocation::class);
        $rules = ['fixed' => [2 => 4000], 'percent' => [1 => 4000, 6 => 6000]];
        $this->assertSame([2 => 4000, 1 => 800, 6 => 1200], $service->targets($rules, 1, 6000));
        $this->assertSame([2 => 3000], $service->targets($rules, 1, 3000));
        $this->assertSame([2 => 8000, 1 => 1600, 6 => 2400], $service->targets($rules, 2, 12000));
        $this->assertSame(1001, array_sum($service->targets(['fixed' => [2 => 333], 'percent' => [1 => 3333, 6 => 6667]], 1, 1001)));
    }

    public function test_partial_payments_and_targeted_refunds_fund_only_the_corresponding_products(): void
    {
        $user = $this->admin();
        $invoice = Invoice::factory()->create(['created_by' => $user->id, 'status' => 'issued', 'total_amount' => 110, 'gst_amount' => 10, 'subtotal_amount' => 100]);
        $first = Product::factory()->create();
        $second = Product::factory()->create();
        $this->config($first, ['fixed' => [2 => 2000], 'percent' => [6 => 5000]]);
        $this->config($second, ['fixed' => [3 => 3000], 'percent' => [1 => 10000]]);
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 1, 'kind' => 'product', 'source_type' => Product::class, 'source_id' => $first->id, 'quantity' => 1, 'line_total_ex_tax' => 40, 'tax_amount' => 4, 'line_total_inc_tax' => 44]);
        $secondLine = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 2, 'kind' => 'product', 'source_type' => Product::class, 'source_id' => $second->id, 'quantity' => 1, 'line_total_ex_tax' => 60, 'tax_amount' => 6, 'line_total_inc_tax' => 66]);
        $payment = Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'cash', 'received_on' => today(), 'total_amount' => 55, 'gst_amount' => 5]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'allocated_amount' => 55]);
        $service = app(InvoiceAllocation::class);
        $service->sync($invoice->fresh(), $user->id);
        $context = $service->context($invoice->fresh());
        $this->assertSame(1000, $context['funding']['categories'][2]);
        $this->assertSame(500, $context['funding']['categories'][6]);
        $this->assertSame(1500, $context['funding']['categories'][3]);
        $this->assertSame(1500, $context['funding']['categories'][1]);
        $payment->update(['total_amount' => 110, 'gst_amount' => 10]);
        $payment->allocations()->update(['allocated_amount' => 110]);
        $adjustment = TaxAdjustment::factory()->create(['invoice_id' => $invoice->id, 'subtotal_amount' => -60, 'gst_amount' => -6, 'total_amount' => -66]);
        TaxAdjustmentLine::factory()->create(['tax_adjustment_id' => $adjustment->id, 'invoice_line_id' => $secondLine->id, 'line_total_ex_tax' => -60, 'tax_amount' => -6, 'line_total_inc_tax' => -66]);
        $refund = Payment::factory()->create(['kind' => 'refund', 'payment_method' => 'cash', 'refund_of_payment_id' => $payment->id, 'received_on' => today(), 'total_amount' => 66, 'gst_amount' => 6]);
        InvoicePaymentAllocation::factory()->create(['invoice_id' => $invoice->id, 'payment_id' => $refund->id, 'tax_adjustment_id' => $adjustment->id, 'allocated_amount' => -66]);
        $context = $service->context($invoice->fresh());
        $this->assertSame(2000, $context['funding']['categories'][2]);
        $this->assertSame(1000, $context['funding']['categories'][6]);
        $this->assertSame(0, $context['funding']['categories'][3]);
        $this->assertSame(0, $context['funding']['categories'][1]);
        $report = app(FinancePlanner::class)->budgetReport($context['budget']);
        $this->assertSame($context['funding']['categories'], $report['funding']['categories']);
        $cash = app(FinancePlanner::class)->cash();
        $this->assertSame(2000, $cash['reserves'][2]);
        $category = DB::table('finance_categories')->find(3);
        $ledger = app(FinancePlanner::class)->costCentreLedger($category);
        $this->assertSame(-3000, $ledger->where('type', 'refund')->sum('amount'));
        $payment->update(['received_on' => today()->subDay()]);
        DB::table('finance_settings')->where('id', 1)->update(['opening_date' => today()->toDateString()]);
        DB::table('finance_categories')->where('id', 3)->update(['opening_cents' => 3000]);
        $this->assertSame(0, app(FinancePlanner::class)->cash()['reserves'][3]);
    }
}
