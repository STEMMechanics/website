<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Organisation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SquareRefundOperation;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderUpdate;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\InvoiceInventory;
use App\Services\SquareApiService;
use App\Services\StoreOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class StoreOrderAdditionalInvoiceTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->admin = User::factory()->create();
        UserGroup::query()->create(['user_id' => $this->admin->id, 'slug' => 'admin']);
    }

    public function test_paid_invoice_is_attached_without_changing_payments_or_reserving_stock_twice(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        app(InvoiceInventory::class)->sync($invoice);
        $this->assertSame(8, $product->fresh()->inventory_quantity);
        $primaryBefore = $order->invoice->getAttributes();
        $paymentBefore = $invoice->allocations()->first()->getAttributes();

        $this->attach($order, $invoice, $line, $product)->assertRedirect(route('admin.shop.order.edit', $order))->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame(2, $order->invoices()->count());
        $this->assertSame(154.0, (float) $order->total_amount);
        $this->assertTrue($order->isPaid());
        $this->assertSame(StoreOrder::STATUS_PROCESSING, $order->status);
        $this->assertSame(8, $product->fresh()->inventory_quantity);
        $this->assertSame([], $invoice->fresh()->inventory_reservations);
        $this->assertSame($primaryBefore, $order->invoice->getAttributes());
        $this->assertSame($paymentBefore, $invoice->allocations()->first()->getAttributes());
        $item = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $this->assertSame(2, $item->inventory_reserved_quantity);
        $this->assertSame(44.0, (float) $item->line_total_amount);
        $this->assertDatabaseHas('store_order_invoice', ['store_order_id' => $order->id, 'invoice_id' => $invoice->id, 'attached_by' => $this->admin->id]);
        $this->assertDatabaseHas('store_order_updates', ['store_order_id' => $order->id, 'event_type' => StoreOrderUpdate::EVENT_INVOICE_ATTACHED]);
        $this->assertSame($order->id, $invoice->fresh()->storeOrders->sole()->id);
        $this->assertNotNull($invoice->fresh()->storeOrderCancellationBlockedReason());
    }

    public function test_review_screen_and_order_pages_show_the_extra_invoice_and_product(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $review = $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.attach', [$order, 'invoice_id' => $invoice->id]))->assertOk();
        $this->assertStringContainsString('Attach invoice and add items', $review->json('html'));
        $this->assertStringContainsString($invoice->invoice_number, $review->json('html'));
        $this->assertStringContainsString($product->title, $review->json('html'));
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $this->get(route('admin.shop.order.edit', $order))->assertOk()->assertSee('Linked invoices')->assertSee($invoice->invoice_number)->assertSee($product->title);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()->assertSee($order->order_number);
        $this->actingAs($order->user)->get(route('account.order.show', $order))->assertOk()
            ->assertSee('Linked invoices')->assertSee($invoice->invoice_number)->assertSee($product->title)->assertSee('$154.00');
        $html = app(StoreOrderService::class)->buildStoreOrderPickListPdf($order->fresh(['items', 'invoices']))->getDomPDF()->outputHtml();
        $this->assertStringContainsString($product->title, $html);
        $this->assertStringContainsString($invoice->invoice_number, $html);
    }

    public function test_unpaid_additions_show_the_combined_balance_and_their_own_payment_link(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order, paid: false);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertFalse($order->isPaid());
        $this->assertSame(44.0, $order->outstandingAmount());
        $this->assertSame(StoreOrder::STATUS_PENDING_PAYMENT, $order->status);
        $this->assertNull($order->paid_at);
        $this->assertTrue($order->items()->where('invoice_line_id', '!=', $line->id)->first()->isPaid());
        $this->assertFalse($order->items()->where('invoice_line_id', $line->id)->first()->isPaid());
        $this->actingAs($order->user)->get(route('account.order.show', $order))->assertOk()
            ->assertSee('Pay invoice')->assertSee(route('account.invoice.show', $invoice), false)->assertSee('$44.00 outstanding');
        $this->settle($invoice);
        app(StoreOrderService::class)->syncOrderState($order->fresh());
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(StoreOrder::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_imported_order_summary_includes_the_original_invoice_even_if_its_pivot_link_is_missing(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $order->invoices()->detach($order->invoice_id);
        $order->invoice->allocations()->delete();
        $order->refresh();

        $this->assertCount(2, $order->invoices);
        $this->assertFalse($order->isPaid());
        $this->assertSame(110.0, $order->outstandingAmount());
        $this->get(route('admin.shop.order.edit', $order))->assertOk()
            ->assertSeeInOrder([$order->invoice->invoice_number, $invoice->invoice_number, 'Delivery Details'])
            ->assertSee('Open primary invoice')->assertSee('Linked invoices')->assertSee('$154.00');
        $this->actingAs($order->user)->get(route('account.order.show', $order))->assertOk()
            ->assertSee($order->invoice->invoice_number)->assertSee($invoice->invoice_number)->assertSee('$110.00 outstanding');
    }

    public function test_attaching_to_an_imported_order_restores_the_primary_invoice_link(): void
    {
        $order = $this->order();
        $order->invoices()->detach();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();

        $this->assertSame(2, $order->invoices()->count());
        $this->assertTrue($order->invoices()->where('invoices.id', $order->invoice_id)->exists());
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(154.0, (float) $order->fresh()->total_amount);
    }

    public function test_added_items_appear_in_the_existing_delivery_summary_without_repricing_shipping(): void
    {
        $order = $this->order();
        $original = $order->items()->firstOrFail();
        $breakdown = ['delivery_estimate_label' => '3-7 business days', 'shipments' => [
            ['type' => 'immediate', 'amount' => 9.95, 'items' => [['display_title' => $original->displayTitle(), 'quantity' => 1]]],
            ['type' => 'delayed', 'amount' => 9.95, 'title_meta' => 'Existing delivery estimate', 'items' => [['display_title' => 'Existing backorder', 'quantity' => 1]]],
        ]];
        $order->update(['shipping_breakdown_data' => $breakdown, 'shipment_count' => 2, 'split_shipments' => true]);
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $order->refresh();

        $summary = $order->shippingBreakdown();
        $this->assertCount(2, $summary['shipments']);
        $this->assertSame($product->title, $summary['shipments'][0]['items'][1]['display_title']);
        $this->assertSame(2, $summary['shipments'][0]['items'][1]['quantity']);
        $this->assertSame(9.95, $summary['shipments'][0]['amount']);
        $this->assertSame($breakdown['shipments'][1]['items'], $summary['shipments'][1]['items']);
        $this->assertSame($summary, $order->shippingBreakdown());
        $this->assertSame($breakdown, $order->shipping_breakdown_data);
        $this->assertSame(154.0, (float) $order->total_amount);
        $this->get(route('admin.shop.order.edit', $order))->assertOk()
            ->assertSeeInOrder(['Delivery Details', $product->title, 'Order Summary']);
    }

    public function test_free_text_line_can_be_mapped_to_a_variant_using_the_invoiced_price(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order, kind: 'generic');
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_quantity' => 5, 'price' => 999]);
        $this->attach($order, $invoice, $line, $product, $variant->id)->assertSessionHasNoErrors();
        $item = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $this->assertSame($variant->id, $item->product_variant_id);
        $this->assertSame(22.0, (float) $item->unit_price);
        $this->assertSame(3, $variant->fresh()->inventory_quantity);
        $this->assertSame(10, $product->fresh()->inventory_quantity);
        $this->assertNull($line->fresh()->source_id);
    }

    public function test_an_invoice_cannot_be_attached_twice_or_to_another_order(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $this->attach($order, $invoice, $line, $product)->assertSessionHasErrors('invoice_id');
        $other = StoreOrder::factory()->create(['user_id' => $order->user_id, 'status' => StoreOrder::STATUS_PROCESSING]);
        $this->attach($other, $invoice, $line, $product)->assertSessionHasErrors('invoice_id');
        $this->assertSame(2, $order->items()->count());
        $this->assertSame(154.0, (float) $order->fresh()->total_amount);
        $this->assertSame(8, $product->fresh()->inventory_quantity);
    }

    public function test_invoices_for_another_customer_are_hidden_and_rejected(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($this->order());
        $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', $order))->assertOk()->assertJsonPath('invoices', []);
        $this->getJson(route('admin.shop.order.invoice.attach', [$order, 'invoice_id' => $invoice->id]))->assertNotFound();
        $this->attach($order, $invoice, $line, $product)->assertSessionHasErrors('invoice_id');
        $this->actingAs($order->user)->post(route('admin.shop.order.invoice.store', $order), [])->assertForbidden();
        $this->assertSame(1, $order->invoices()->count());
    }

    public function test_invoiced_product_identity_and_variant_cannot_be_replaced(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $replacement = Product::factory()->create();
        $this->attach($order, $invoice, $line, $replacement)->assertSessionHasErrors('items.'.$line->id.'.selection');
        $variant = ProductVariant::factory()->create(['product_id' => $replacement->id]);
        $this->attach($order, $invoice, $line, $product, $variant->id)->assertSessionHasErrors('items.'.$line->id.'.selection');
        $this->assertSame(1, $order->items()->count());
    }

    public function test_fulfilled_orders_and_draft_or_credited_invoices_are_rejected(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $order->update(['status' => StoreOrder::STATUS_SHIPPED]);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasErrors('invoice_id');
        $order->update(['status' => StoreOrder::STATUS_PROCESSING]);
        $invoice->update(['status' => Invoice::STATUS_DRAFT]);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasErrors('invoice_id');
        $invoice->update(['status' => Invoice::STATUS_PAID]);
        TaxAdjustment::factory()->create(['invoice_id' => $invoice->id]);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasErrors('invoice_id');
    }

    public function test_omitting_a_product_line_does_not_release_its_reservation(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $second = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 2, 'kind' => 'product', 'source_type' => Product::class, 'source_id' => $product->id, 'quantity' => 1]);
        app(InvoiceInventory::class)->sync($invoice);
        $reservations = $invoice->fresh()->inventory_reservations;
        $this->attach($order, $invoice, $line, $product)->assertSessionHasErrors('items');
        $this->assertSame(7, $product->fresh()->inventory_quantity);
        $this->assertSame($reservations, $invoice->fresh()->inventory_reservations);
        $this->assertDatabaseMissing('store_order_items', ['invoice_line_id' => $second->id]);
    }

    public function test_a_stock_failure_rolls_back_every_imported_item_and_reservation(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        app(InvoiceInventory::class)->sync($invoice);
        $empty = Product::factory()->create(['inventory_quantity' => 0]);
        $second = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'line_number' => 2, 'kind' => 'generic', 'quantity' => 1, 'line_total_inc_tax' => 11]);
        $this->actingAs($this->admin)->post(route('admin.shop.order.invoice.store', $order), [
            'invoice_id' => $invoice->id, 'shipment_plan' => 'same_shipment',
            'items' => [
                $line->id => ['invoice_line_id' => $line->id, 'selection' => $product->id.':0'],
                $second->id => ['invoice_line_id' => $second->id, 'selection' => $empty->id.':0'],
            ],
        ])->assertSessionHasErrors('items');
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(1, $order->invoices()->count());
        $this->assertSame(110.0, (float) $order->fresh()->total_amount);
        $this->assertSame(8, $product->fresh()->inventory_quantity);
        $this->assertNotEmpty($invoice->fresh()->inventory_reservations);
        $this->assertDatabaseMissing('store_order_updates', ['event_type' => StoreOrderUpdate::EVENT_INVOICE_ATTACHED]);
    }

    public function test_cancelling_an_added_item_credits_only_its_invoice_and_restores_its_stock(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $item = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $this->post(route('admin.shop.order.item.cancel', [$order, $item]), ['available_quantity' => 1, 'reason' => 'Remove one additional item'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $adjustment = TaxAdjustment::query()->sole();
        $this->assertSame($invoice->id, $adjustment->invoice_id);
        $this->assertSame(-22.0, (float) $adjustment->total_amount);
        $this->assertSame($line->id, $adjustment->lines->sole()->invoice_line_id);
        $this->assertSame(9, $product->fresh()->inventory_quantity);
        $this->assertSame($invoice->id, SquareRefundOperation::query()->sole()->invoice_id);
        $this->assertSame(0, $order->invoice->taxAdjustments()->count());
    }

    public function test_batch_cancellation_keeps_discounts_and_shipping_on_their_respective_invoices(): void
    {
        $order = $this->order();
        $primary = $order->invoice;
        $this->chargeLine($primary, 'shipping', 11, 2);
        $this->chargeLine($primary, 'discount', -22, 3);
        $primary->update(['subtotal_amount' => 90, 'gst_amount' => 9, 'total_amount' => 99]);
        $primary->allocations()->update(['allocated_amount' => 99]);
        $order->update(['shipping_amount' => 11, 'discount_amount' => 22, 'coupon_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT, 'gst_amount' => 9, 'total_amount' => 99]);
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $this->chargeLine($invoice, 'shipping', 11, 2);
        $this->chargeLine($invoice, 'discount', -4.4, 3);
        $invoice->update(['subtotal_amount' => 46, 'gst_amount' => 4.6, 'total_amount' => 50.6]);
        $invoice->allocations()->update(['allocated_amount' => 50.6]);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $actions = $order->items()->get()->map(fn ($item) => ['type' => 'cancel', 'item_id' => $item->id, 'quantity' => $item->quantity, 'reason' => 'Cancel entire order'])->all();
        $this->put(route('admin.shop.order.update', $order), ['status' => StoreOrder::STATUS_PROCESSING, 'item_actions_json' => json_encode($actions)])
            ->assertRedirect()->assertSessionHasNoErrors();
        $credits = TaxAdjustment::query()->with('lines')->get()->keyBy('invoice_id');
        $this->assertCount(2, $credits);
        $this->assertSame(-99.0, (float) $credits[$primary->id]->total_amount);
        $this->assertSame(-50.6, (float) $credits[$invoice->id]->total_amount);
        foreach ($credits as $invoiceId => $credit) {
            $this->assertTrue($credit->lines->every(fn ($creditLine) => (int) InvoiceLine::find($creditLine->invoice_line_id)->invoice_id === (int) $invoiceId));
        }
        $this->assertSame(StoreOrder::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(10, $product->fresh()->inventory_quantity);
        $this->assertSame(2, SquareRefundOperation::query()->count());
    }

    public function test_square_refund_uses_the_additional_payment(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $payment = $invoice->allocations()->first()->customerPayment;
        $payment->forceFill(['payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD, 'gateway_provider' => 'square', 'gateway_status' => 'COMPLETED', 'square_payment_id' => 'sq-additional', 'square_paid_money_amount' => 4400])->save();
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $api = Mockery::mock(SquareApiService::class);
        $api->shouldReceive('isEnabled')->andReturn(true);
        $api->shouldReceive('createRefund')->once()->with(Mockery::on(fn (array $payload): bool => $payload['payment_id'] === 'sq-additional' && $payload['amount_money']['amount'] === 2200))
            ->andReturn(['refund' => ['id' => 'sq-extra-refund', 'status' => 'COMPLETED', 'amount_money' => ['amount' => 2200]]]);
        $this->app->instance(SquareApiService::class, $api);
        $item = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $this->post(route('admin.shop.order.item.cancel', [$order, $item]), ['available_quantity' => 1, 'reason' => 'Remove additional item'])->assertSessionHasNoErrors();
        $refund = Payment::query()->where('kind', Payment::KIND_REFUND)->sole();
        $this->assertSame($payment->id, $refund->refund_of_payment_id);
        $this->assertSame($invoice->id, SquareRefundOperation::query()->sole()->invoice_id);
    }

    public function test_a_part_paid_addition_records_a_manual_refund_for_only_the_amount_paid(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order, paid: false);
        $payment = Payment::factory()->create(['user_id' => $order->user_id, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'total_amount' => 11]);
        InvoicePaymentAllocation::query()->create(['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'allocated_amount' => 11]);
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $item = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $this->post(route('admin.shop.order.item.cancel', [$order, $item]), ['available_quantity' => 2, 'reason' => 'Cancel the addition'])
            ->assertSessionHasNoErrors()->assertSessionHas('message', fn ($message) => str_contains($message, 'Manual refund still required'));
        $operation = SquareRefundOperation::query()->sole();
        $this->assertSame($invoice->id, $operation->invoice_id);
        $this->assertSame(1100, $operation->requested_cents);
        $this->assertSame(0.0, $invoice->fresh()->outstandingAmount());
        $this->assertSame(0, $order->invoice->taxAdjustments()->count());
    }

    public function test_separate_shipment_and_backorder_details_are_preserved(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order, kind: 'generic');
        $product->update(['inventory_quantity' => 1, 'allow_backorder' => true, 'backorder_shipping_estimate' => now()->addDays(14)]);
        $this->attach($order, $invoice, $line, $product, shipmentPlan: 'separate_shipment')->assertSessionHasNoErrors();
        $item = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $this->assertSame(1, $item->available_now_quantity);
        $this->assertSame(1, $item->delayed_quantity);
        $this->assertSame('backorder', $item->delayed_fulfilment_type);
        $this->assertTrue($order->fresh()->split_shipments);
        $this->assertSame(2, $order->fresh()->shipment_count);
        $summary = $order->fresh()->shippingBreakdown();
        $this->assertCount(2, $summary['shipments']);
        $this->assertCount(1, $summary['shipments'][1]['items']);
        $this->assertSame($product->title, $summary['shipments'][1]['items'][0]['display_title']);
        $this->assertSame(2, $summary['shipments'][1]['items'][0]['quantity']);
        $this->assertSame(now()->addDays(14)->toDateString(), $summary['shipments'][1]['dispatch_date']);
    }

    public function test_equal_refunds_on_different_invoices_create_distinct_operations(): void
    {
        $order = $this->order();
        $original = $order->items()->firstOrFail();
        $original->update(['quantity' => 5, 'available_now_quantity' => 5, 'inventory_reserved_quantity' => 5, 'unit_price' => 22]);
        $original->invoiceLine->update(['quantity' => 5, 'unit_price_ex_tax' => 20]);
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        foreach ([$order->invoice, $invoice] as $paymentInvoice) {
            $paymentInvoice->allocations()->first()->customerPayment->forceFill([
                'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD, 'gateway_provider' => 'square',
                'gateway_status' => 'COMPLETED', 'square_payment_id' => 'sq-invoice-'.$paymentInvoice->id,
                'square_paid_money_amount' => (int) round((float) $paymentInvoice->total_amount * 100),
            ])->save();
        }
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $added = $order->items()->where('invoice_line_id', $line->id)->firstOrFail();
        $api = Mockery::mock(SquareApiService::class);
        $api->shouldReceive('isEnabled')->andReturn(true);
        $api->shouldReceive('createRefund')->twice()->with(Mockery::on(fn ($payload): bool => $payload['amount_money']['amount'] === 2200))
            ->andReturnUsing(fn ($payload): array => ['refund' => ['id' => 'refund-'.$payload['payment_id'], 'status' => 'COMPLETED', 'amount_money' => ['amount' => 2200]]]);
        $this->app->instance(SquareApiService::class, $api);
        $this->put(route('admin.shop.order.update', $order), [
            'status' => StoreOrder::STATUS_PROCESSING,
            'item_actions_json' => json_encode([
                ['type' => 'cancel', 'item_id' => $original->id, 'quantity' => 1, 'reason' => 'Remove one'],
                ['type' => 'cancel', 'item_id' => $added->id, 'quantity' => 1, 'reason' => 'Remove one extra'],
            ]),
        ])->assertSessionHasNoErrors();
        $operations = SquareRefundOperation::query()->get();
        $this->assertCount(2, $operations);
        $this->assertSame(2, $operations->pluck('idempotency_key')->unique()->count());
        $this->assertTrue($operations->every(fn ($operation) => $operation->status === SquareRefundOperation::STATUS_COMPLETED));
    }

    public function test_migration_backfills_existing_primary_invoice_links(): void
    {
        $order = $this->order();
        $migration = require database_path('migrations/2026_10_13_100000_create_store_order_invoice_table.php');
        $migration->down();
        $migration->up();
        $this->assertSame($order->invoice_id, $order->fresh()->invoices->sole()->id);
        $this->assertSame($order->id, $order->invoice->fresh()->storeOrders->sole()->id);
    }

    public function test_popup_lists_only_the_latest_five_eligible_invoices_with_store_items(): void
    {
        $order = $this->order();
        $ids = [];
        for ($index = 0; $index < 7; $index++) {
            [$invoice] = $this->additionalInvoice($order);
            $ids[] = $invoice->id;
        }
        $this->additionalInvoice($order, kind: 'generic');
        [$draft] = $this->additionalInvoice($order);
        $draft->update(['status' => Invoice::STATUS_DRAFT]);
        [$credited] = $this->additionalInvoice($order);
        TaxAdjustment::factory()->create(['invoice_id' => $credited->id]);
        $this->additionalInvoice($this->order());

        $response = $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', $order))
            ->assertOk()->assertJsonCount(5, 'invoices')->assertJsonPath('invoices.0.payment', 'Paid');
        $this->assertSame(array_slice(array_reverse($ids), 0, 5), array_column($response->json('invoices'), 'id'));
        $this->get(route('admin.shop.order.edit', $order))->assertOk()->assertSee('id="order-invoice-attachment"', false)
            ->assertSee('Search invoices')->assertDontSee('name="invoice_id"', false);
        $this->get(route('admin.shop.order.invoice.attach', [$order, 'invoice_id' => $ids[0]]))
            ->assertRedirect(route('admin.shop.order.edit', [$order, 'attach_invoice' => $ids[0]]));
    }

    public function test_text_search_finds_older_invoices_and_limits_suggestions(): void
    {
        $order = $this->order();
        [$older, $line] = $this->additionalInvoice($order);
        $line->update(['description' => 'Rare robotics add-on']);
        for ($index = 0; $index < 10; $index++) {
            $this->additionalInvoice($order);
        }
        [$other, $otherLine] = $this->additionalInvoice($this->order());
        $otherLine->update(['description' => 'Rare robotics add-on']);
        $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => 'Rare robotics']))
            ->assertOk()->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.id', $older->id);
        $this->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => $older->invoice_number]))
            ->assertOk()->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.id', $older->id);
        $this->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => 'Additional STEM kit']))
            ->assertOk()->assertJsonCount(8, 'invoices');
        $this->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => $other->invoice_number]))
            ->assertOk()->assertJsonPath('invoices', []);
        $this->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => str_repeat('a', 151)]))
            ->assertUnprocessable()->assertJsonValidationErrors('q');
        $this->actingAs($order->user)->getJson(route('admin.shop.order.invoice.options', $order))->assertForbidden();
    }

    public function test_an_invoice_for_another_contact_at_the_same_company_can_be_found_and_attached(): void
    {
        $order = $this->order();
        $order->update(['billing_company' => 'STEM Academy']);
        [$invoice, $line, $product] = $this->additionalInvoice($this->order());
        $invoice->update(['billing_company' => ' stem academy ']);
        $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => 'academy']))
            ->assertOk()->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.id', $invoice->id);
        $this->getJson(route('admin.shop.order.invoice.attach', [$order, 'invoice_id' => $invoice->id]))->assertOk();
        $this->attach($order, $invoice, $line, $product)->assertSessionHasNoErrors();
        $this->assertSame(2, $order->invoices()->count());
        $this->assertNotSame($order->user_id, $invoice->fresh()->user_id);

        $page = $this->actingAs($order->user)->get(route('account.order.show', $order))->assertOk();
        $linked = collect($page->viewData('linkedInvoices'))->firstWhere('number', $invoice->invoice_number);
        $this->assertSame(route('invoice.public.pay.show', $invoice), $linked['view_url']);
        $this->get($linked['pdf_url'])->assertOk()->assertHeader('content-type', 'application/pdf');
        $receipt = collect($page->viewData('receiptLinks'))->firstWhere('invoice_id', $invoice->id);
        $this->get($receipt['download_url'])->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_company_matching_uses_primary_organisations_when_billing_company_is_empty(): void
    {
        $organisation = Organisation::factory()->create(['name' => 'Robotics College']);
        $order = $this->order();
        $order->user->update(['primary_organisation_id' => $organisation->id]);
        $other = $this->order();
        $other->user->update(['primary_organisation_id' => $organisation->id]);
        [$invoice] = $this->additionalInvoice($other);
        [$differentCompany] = $this->additionalInvoice($other);
        $differentCompany->update(['billing_company' => 'Another Company']);
        $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', [$order, 'q' => 'Robotics College']))
            ->assertOk()->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.id', $invoice->id)
            ->assertJsonPath('invoices.0.company', 'Robotics College');
    }

    public function test_registered_customer_can_find_a_guest_invoice_with_the_same_billing_email(): void
    {
        $order = $this->order();
        [$invoice] = $this->additionalInvoice($order);
        $invoice->update(['user_id' => null, 'billing_email' => ' '.strtoupper($order->billing_email).' ']);
        $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', $order))
            ->assertOk()->assertJsonCount(1, 'invoices')->assertJsonPath('invoices.0.id', $invoice->id);
    }

    public function test_popup_submission_returns_validation_errors_or_the_updated_order_url(): void
    {
        $order = $this->order();
        [$invoice, $line, $product] = $this->additionalInvoice($order);
        $payload = ['invoice_id' => $invoice->id, 'shipment_plan' => 'same_shipment', 'items' => [
            $line->id => ['invoice_line_id' => $line->id, 'selection' => '0:0'],
        ]];
        $this->actingAs($this->admin)->postJson(route('admin.shop.order.invoice.store', $order), $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->assertSame(1, $order->invoices()->count());
        $payload['items'][$line->id]['selection'] = $product->id.':0';
        $this->postJson(route('admin.shop.order.invoice.store', $order), $payload)->assertOk()
            ->assertJsonPath('redirect_url', route('admin.shop.order.edit', $order));
        $this->assertSame(2, $order->invoices()->count());
    }

    public function test_legacy_primary_invoice_is_excluded_even_if_its_pivot_link_is_missing(): void
    {
        $order = $this->order();
        $order->invoices()->detach();
        $this->actingAs($this->admin)->getJson(route('admin.shop.order.invoice.options', $order))
            ->assertOk()->assertJsonPath('invoices', []);
        $this->getJson(route('admin.shop.order.invoice.attach', [$order, 'invoice_id' => $order->invoice_id]))->assertNotFound();
    }

    private function order(): StoreOrder
    {
        $customer = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $customer->id, 'billing_email' => $customer->email, 'status' => Invoice::STATUS_PAID, 'subtotal_amount' => 100, 'gst_amount' => 10, 'total_amount' => 110, 'issued_at' => now()]);
        $product = Product::factory()->create(['inventory_quantity' => 9]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'product', 'quantity' => 1, 'unit_price_ex_tax' => 100, 'line_total_ex_tax' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110, 'source_type' => Product::class, 'source_id' => $product->id]);
        $order = StoreOrder::factory()->create(['user_id' => $customer->id, 'invoice_id' => $invoice->id, 'billing_email' => $customer->email, 'status' => StoreOrder::STATUS_PROCESSING, 'subtotal_amount' => 110, 'shipping_amount' => 0, 'discount_amount' => 0, 'gst_amount' => 10, 'total_amount' => 110, 'shipment_count' => 1, 'split_shipments' => false, 'shipping_method_code' => 'regular', 'paid_at' => now()]);
        StoreOrderItem::factory()->create(['store_order_id' => $order->id, 'product_id' => $product->id, 'invoice_line_id' => $line->id, 'product_title' => $product->title, 'quantity' => 1, 'available_now_quantity' => 1, 'delayed_quantity' => 0, 'inventory_reserved_quantity' => 1, 'unit_price' => 110, 'line_price_amount' => 110, 'line_total_amount' => 110, 'line_gst_amount' => 10]);
        $this->settle($invoice);

        return $order;
    }

    private function additionalInvoice(StoreOrder $order, bool $paid = true, string $kind = 'product'): array
    {
        $invoice = Invoice::factory()->create(['user_id' => $order->user_id, 'billing_email' => $order->billing_email, 'status' => $paid ? Invoice::STATUS_PAID : Invoice::STATUS_ISSUED, 'subtotal_amount' => 40, 'gst_amount' => 4, 'total_amount' => 44, 'issued_at' => now()]);
        $product = Product::factory()->create(['title' => 'Additional STEM kit', 'price' => 99, 'inventory_quantity' => 10]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => $kind, 'description' => 'Additional STEM kit', 'quantity' => 2, 'unit_price_ex_tax' => 20, 'tax_rate' => 0.1, 'line_total_ex_tax' => 40, 'tax_amount' => 4, 'line_total_inc_tax' => 44, 'source_type' => $kind === 'product' ? Product::class : null, 'source_id' => $kind === 'product' ? $product->id : null]);
        if ($paid) {
            $this->settle($invoice);
        }

        return [$invoice, $line, $product];
    }

    private function settle(Invoice $invoice): void
    {
        $payment = Payment::factory()->create(['user_id' => $invoice->user_id, 'payment_method' => Payment::PAYMENT_METHOD_CASH, 'total_amount' => $invoice->total_amount, 'gst_amount' => $invoice->gst_amount]);
        InvoicePaymentAllocation::query()->create(['invoice_id' => $invoice->id, 'payment_id' => $payment->id, 'allocated_amount' => $invoice->total_amount]);
    }

    private function attach(StoreOrder $order, Invoice $invoice, InvoiceLine $line, Product $product, int $variantId = 0, string $shipmentPlan = 'same_shipment')
    {
        return $this->actingAs($this->admin)->post(route('admin.shop.order.invoice.store', $order), [
            'invoice_id' => $invoice->id,
            'shipment_plan' => $shipmentPlan,
            'items' => [$line->id => ['invoice_line_id' => $line->id, 'selection' => $product->id.':'.$variantId]],
        ]);
    }

    private function chargeLine(Invoice $invoice, string $kind, float $amount, int $lineNumber): InvoiceLine
    {
        $net = round($amount / 1.1, 2);

        return InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'line_number' => $lineNumber, 'kind' => $kind, 'description' => ucfirst($kind), 'quantity' => 1, 'unit_price_ex_tax' => $net, 'line_total_ex_tax' => $net, 'tax_amount' => round($amount - $net, 2), 'line_total_inc_tax' => $amount]);
    }
}
