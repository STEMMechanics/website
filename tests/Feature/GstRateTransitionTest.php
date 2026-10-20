<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Location;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\AdminWorkshopTicketService;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\GstRate;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\SponsorshipTaxService;
use App\Services\Finance\WorkshopCosting;
use App\Services\Finance\WorkshopFunding;
use App\Services\QuoteWorkflowService;
use App\Services\SquareWebhookSyncService;
use App\Services\StoreCartService;
use App\Services\StoreOrderService;
use App\Support\RequestMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class GstRateTransitionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);

        return $user;
    }

    private function futureRate(): void
    {
        DB::table('finance_gst_rates')->insert(['effective_from' => '2027-07-01', 'rate' => 0.15, 'created_at' => now(), 'updated_at' => now()]);
        app(RequestMemo::class)->clear();
    }

    private function line(): array
    {
        return ['kind' => 'custom', 'description' => 'GST transition test', 'quantity' => 10, 'unit_price_inc_tax' => 11.50, 'gst_applicable' => true];
    }

    public function test_scheduled_rate_starts_at_local_midnight_without_changing_the_past(): void
    {
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 6, 30)->setTime(23, 59, 59));
        $this->assertSame(0.10, GstRate::current());
        $this->travelTo(now()->addSecond());
        $this->assertSame(0.15, GstRate::current());
        $this->assertSame(0.10, GstRate::forDate('2026-10-20'));
        $this->assertSame(0.15, GstRate::forDate('2027-07-01'));
        $this->assertSame(0.10, GstRate::forDate('2027-06-30T13:59:59Z'));
        $this->assertSame(0.15, GstRate::forDate('2027-06-30T14:00:00Z'));
    }

    public function test_admin_can_schedule_future_rates_but_cannot_rewrite_rate_history(): void
    {
        $this->admin();
        $this->travelTo(now()->setDate(2026, 10, 20));
        $this->get(route('admin.finance.gst-rates'))->assertOk()->assertSee('10.00%');
        $this->post(route('admin.finance.gst-rates.store'), ['percentage' => '15', 'effective_from' => '2027-07-01'])->assertSessionHasNoErrors();
        $this->assertSame(0.10, GstRate::current());
        $this->assertSame(0.15, GstRate::forDate('2027-07-01'));
        foreach ([['percentage' => 101, 'effective_from' => '2028-01-01'], ['percentage' => 15, 'effective_from' => '2026-10-19'], ['percentage' => 15, 'effective_from' => '2027-07-01']] as $invalid) {
            $this->post(route('admin.finance.gst-rates.store'), $invalid)->assertSessionHasErrors();
        }
        $baseline = DB::table('finance_gst_rates')->where('effective_from', '2000-07-01')->value('id');
        $this->delete(route('admin.finance.gst-rates.destroy', $baseline))->assertStatus(422);
        $scheduled = DB::table('finance_gst_rates')->where('effective_from', '2027-07-01')->value('id');
        $this->delete(route('admin.finance.gst-rates.destroy', $scheduled))->assertRedirect();
        $this->assertSame(0.10, GstRate::forDate('2027-07-01'));
    }

    public function test_new_quotes_invoices_products_and_sponsorships_use_the_current_rate(): void
    {
        $user = $this->admin();
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 7, 1));
        $this->post(route('admin.quote.store'), [
            'quote_number' => 'GST-NEW', 'user_id' => $user->id, 'quote_date' => '2027-07-01', 'status' => Quote::STATUS_OPEN,
            'title' => 'New rate', 'line_items_json' => json_encode([$this->line()]),
        ])->assertSessionHasNoErrors();
        $quote = Quote::where('quote_number', 'GST-NEW')->firstOrFail();
        $this->assertSame(0.15, $quote->line_items[0]['tax_rate']);
        $this->assertSame('100.00', $quote->subtotal_amount);
        $this->assertSame('15.00', $quote->gst_amount);
        $this->assertSame('115.00', $quote->total_amount);
        $this->post(route('admin.invoice.store'), ['invoice_number' => 'GST-NEW', 'issue_date' => '2027-07-01', 'line_items_json' => json_encode([$this->line()])])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'GST-NEW')->firstOrFail();
        $this->assertSame('0.1500', $invoice->lines->first()->tax_rate);
        $this->assertSame('115.00', $invoice->total_amount);
        $this->assertSame('0.1500', Product::factory()->create(['tax_rate' => 0.1])->tax_rate);
        $this->assertSame('0.0000', Product::factory()->create(['tax_rate' => 0])->tax_rate);
        $tax = app(SponsorshipTaxService::class)->breakdown(115, 'AU', false);
        $this->assertSame(100.0, $tax['subtotal']);
        $this->assertSame(15.0, $tax['gst_amount']);
    }

    public function test_old_quote_printing_saving_and_invoice_conversion_retain_the_original_rate(): void
    {
        $this->admin();
        $quote = Quote::factory()->create(['status' => Quote::STATUS_OPEN, 'quote_date' => '2026-10-20', 'subtotal_amount' => 100, 'gst_amount' => 10, 'total_amount' => 110,
            'line_items' => [['kind' => 'custom', 'description' => 'Legacy quote without an explicit tax rate', 'quantity' => 10, 'unit_price' => 10, 'line_total' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110, 'gst_applicable' => true]],
        ]);
        $before = view('pdf.quote', ['quote' => $quote])->render();
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 7, 1));
        $quote->refresh();
        $this->assertSame(0.10, $quote->line_items[0]['tax_rate']);
        $this->assertSame($before, view('pdf.quote', ['quote' => $quote])->render());
        $this->put(route('admin.quote.update', $quote), ['quote_number' => $quote->quote_number, 'user_id' => $quote->user_id, 'status' => Quote::STATUS_OPEN, 'quote_date' => '2026-10-20', 'title' => 'Old quote', 'line_items_json' => json_encode($quote->line_items)])->assertSessionHasNoErrors();
        $quote->refresh();
        $this->assertSame('110.00', $quote->total_amount);
        $invoice = app(QuoteWorkflowService::class)->createInvoiceFromQuote($quote);
        $this->assertSame('0.1000', $invoice->lines->first()->tax_rate);
        $this->assertSame('110.00', $invoice->total_amount);
    }

    public function test_old_invoice_pdf_and_credit_details_use_the_original_rate_after_a_change(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create(['status' => Invoice::STATUS_PAID, 'subtotal_amount' => 100, 'gst_amount' => 10, 'total_amount' => 110]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'quantity' => 1, 'unit_price_ex_tax' => 100, 'tax_rate' => 0.1, 'line_total_ex_tax' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110]);
        $data = ['invoice' => $invoice->load('lines'), 'itemPages' => [$invoice->lines->toArray()], 'adjustments' => collect()];
        $before = view('pdf.invoice', $data)->render();
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 7, 1));
        $this->assertSame($before, view('pdf.invoice', $data)->render());
        $this->assertSame('0.1000', $line->fresh()->tax_rate);
        $this->assertSame(0.10, GstRate::forLine($line));
        $this->post(route('admin.tax_adjustment.store', $invoice), ['refund_qty' => [$line->id => '0.5'], 'reason' => 'Partial refund after a GST rate change.'])->assertSessionHasNoErrors();
        $credit = TaxAdjustment::query()->sole();
        $this->assertSame('-50.00', $credit->subtotal_amount);
        $this->assertSame('-5.00', $credit->gst_amount);
        $this->assertSame('-55.00', $credit->total_amount);
        $this->assertSame('0.1000', $credit->lines->first()->tax_rate);
    }

    public function test_workshop_costing_uses_fifteen_percent_without_altering_allocation_costs(): void
    {
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 7, 1));
        $version = (object) ['rules' => json_encode([['category_id' => 2, 'basis' => 'participant', 'rate_cents' => 1000]]), 'prices' => json_encode(['rounding_step' => 0, 'travel_rounding_step' => 0])];
        $costing = app(WorkshopCosting::class)->calculate($version, ['hours' => 1, 'participants' => 10, 'travel_hours' => 0]);
        $this->assertSame(10000, $costing['cost']);
        $this->assertSame(11500, $costing['gross']);
        $this->assertSame(1500, $costing['tax']);
    }

    public function test_store_cart_order_and_manual_tickets_record_current_gst(): void
    {
        $admin = $this->admin();
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 7, 1));
        $product = Product::factory()->create(['product_type' => Product::PRODUCT_TYPE_DIGITAL, 'price' => 115, 'tax_rate' => 0.15]);
        $cart = app(StoreCartService::class);
        $cart->add($product);
        $this->assertSame(15.0, $cart->summary()['gst']);
        $order = app(StoreOrderService::class)->createFromCart($cart->lines(), ['billing_name' => 'GST Test', 'billing_email' => 'gst@example.test', 'billing_phone' => '0400000000'], sendNotifications: false);
        $this->assertSame('15.00', $order->gst_amount);
        $this->assertSame('0.1500', $order->items->first()->tax_rate);
        Media::factory()->create(['name' => 'stemmechanics-logo.png', 'user_id' => $admin->id]);
        $location = Location::factory()->create();
        $workshop = Workshop::factory()->create(['user_id' => $admin->id, 'location_id' => $location->id, 'registration' => 'tickets', 'price' => '115.00', 'max_tickets' => 10]);
        $ticket = app(AdminWorkshopTicketService::class)->create($workshop, ['firstname' => 'GST', 'surname' => 'Participant', 'email' => 'participant@example.test', 'phone' => '0400000000'], 'reserve');
        $this->assertSame('0.1500', $ticket['invoice']->lines->first()->tax_rate);
        $this->assertSame('100.00', $ticket['invoice']->subtotal_amount);
        $this->assertSame('15.00', $ticket['invoice']->gst_amount);
    }

    public function test_delayed_gateway_import_uses_the_original_payment_date(): void
    {
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 8, 1));
        foreach (['2027-06-30T12:00:00Z' => 10.0, '2027-07-01T12:00:00Z' => 14.35] as $date => $expectedGst) {
            $result = app(SquareWebhookSyncService::class)->syncPayload(['type' => 'payment.created', 'data' => ['object' => ['payment' => [
                'id' => 'GST-'.substr($date, 0, 10), 'status' => 'COMPLETED', 'application_details' => ['square_product' => 'SQUARE_POS'],
                'amount_money' => ['amount' => 11000, 'currency' => 'AUD'], 'created_at' => $date,
            ]]]]);
            $this->assertNotNull($result['payment']);
            $this->assertSame($expectedGst, (float) $result['payment']->gst_amount);
        }
    }

    public function test_allocation_rounding_preserves_the_funding_invoice_rate(): void
    {
        $admin = $this->admin();
        $location = Location::factory()->create();
        Media::factory()->create(['name' => 'stemmechanics-logo.png', 'user_id' => $admin->id]);
        $this->futureRate();
        $this->travelTo(now()->setDate(2027, 7, 1));
        DB::table('finance_pricing_versions')->where('id', 1)->update([
            'rules' => json_encode([['category_id' => 1, 'basis' => 'workshop', 'rate_cents' => 10000], ['category_id' => 2, 'basis' => 'participant', 'rate_cents' => 500]]),
            'prices' => json_encode(['rounding_step' => 100, 'rounding_category_id' => 2, 'pricing_participants' => 10]),
        ]);
        $planner = app(FinancePlanner::class);
        foreach (['2027-06-30' => [0.10, 909], '2027-07-01' => [0.15, 1304]] as $date => [$rate, $roundingLimit]) {
            $workshop = Workshop::factory()->create(['user_id' => $admin->id, 'location_id' => $location->id, 'starts_at' => '2027-07-10 10:00:00', 'ends_at' => '2027-07-10 11:00:00']);
            $invoice = Invoice::factory()->create(['issue_date' => $date, 'status' => Invoice::STATUS_ISSUED]);
            InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'workshop', 'tax_rate' => $rate,
                'details_json' => ['workshop' => ['linked_workshop_id' => $workshop->id, 'hours' => 1, 'seats' => 20, 'venue_supplied' => false]],
            ]);
            $preview = $planner->preview(['version_id' => 1, 'invoice_ids' => [$invoice->id], 'participants' => 20, 'hours' => 1, 'travel_minutes' => 0, 'venue_supplied' => false]);
            $row = $preview['rows'][0];
            $this->assertSame($rate, $row['assumptions']['tax_rate']);
            $version = DB::table('finance_pricing_versions')->find(1);
            $this->assertSame($roundingLimit, $planner->rounding($version, $row['assumptions'])['limit']);
            $this->assertSame($rate, app(WorkshopFunding::class)->assumptions($workshop)['tax_rate']);
            $this->assertSame($rate, app(InvoiceAllocation::class)->context($invoice, null, null, $workshop)['assumptions']['tax_rate']);
        }
    }

    public function test_price_policies_follow_each_change_without_double_adjusting_saved_prices(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 20));
        $product = Product::factory()->create(['price' => 110, 'tax_rate' => 0.1]);
        $free = Product::factory()->create(['price' => 110, 'tax_rate' => 0]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'price' => 220]);
        $workshop = new Workshop(['price' => '$110.00', 'early_bird_price' => 55]);
        $this->futureRate();
        DB::table('finance_gst_rates')->insert([
            ['effective_from' => '2028-07-01', 'rate' => 0.20, 'price_basis' => 'inclusive', 'created_at' => now(), 'updated_at' => now()],
            ['effective_from' => '2029-07-01', 'rate' => 0.25, 'price_basis' => 'exclusive', 'created_at' => now(), 'updated_at' => now()],
        ]);
        app(RequestMemo::class)->clear();
        $this->travelTo(now()->setDate(2027, 7, 1));
        $this->assertSame('115.00', $product->price);
        $this->assertSame(230.0, $variant->effectivePrice());
        $this->assertSame('$115.00', $workshop->price);
        $this->assertSame('57.50', $workshop->early_bird_price);
        $this->assertSame('110.00', $free->price);
        $product->price = '115.00';
        $product->save();
        $this->assertSame('115.00', $product->fresh()->price);
        $this->assertSame(230.0, $variant->fresh()->effectivePrice());
        $this->travelTo(now()->setDate(2028, 7, 1));
        $this->assertSame('115.00', $product->fresh()->price);
        $this->assertSame(230.0, $variant->fresh()->effectivePrice());
        $this->travelTo(now()->setDate(2029, 7, 1));
        $this->assertSame('119.79', $product->fresh()->price);
        $this->assertSame(239.58, $variant->fresh()->effectivePrice());
        $this->assertSame('$119.79', $workshop->price);
    }
}
