<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Jobs\SendDeferredStoreOrderEmail;
use App\Jobs\SendWorkshopTicketOrderEmail;
use App\Mail\StoreOrderAdminNotification;
use App\Mail\StoreOrderConfirmation;
use App\Mail\StoreOrderPaid;
use App\Mail\TicketAttendeeUpdate;
use App\Mail\TicketOrderConfirmation;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Location;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopTicketEmail;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WorkshopTicketEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.altcha_enabled' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_equipment_selected_on_first_checkout_page_is_carried_to_delivery_review(): void
    {
        Queue::fake();
        $product = \App\Models\Product::factory()->create(['status' => 'active', 'product_type' => 'physical', 'price' => 32, 'inventory_quantity' => 10]);
        $workshop = $this->createTicketedWorkshop(['optional_product_ids' => [$product->id]]);
        $this->get(route('workshop.ticket.flow.start', $workshop))->assertOk()->assertSee('Continue')->assertDontSee('Continue to Payment')
            ->assertDontSee('equipment_quantities['.$product->id.']', false);
        $payload = ['quantity' => 2, 'firstname' => 'Jamie', 'surname' => 'Example', 'email' => 'equipment@example.com', 'phone' => '0400123456', 'equipment_quantities' => [$product->id => 1]];
        $this->post(route('workshop.ticket.flow.begin', $workshop), $payload)->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.ticket.flow.equipment', $workshop));
        $lines = app(\App\Services\WorkshopEquipmentService::class)->cart($workshop)->contents()['lines'];
        $this->assertCount(1, $lines);
        $this->assertSame(1, (int) array_values($lines)[0]['quantity']);
        $this->get(route('workshop.ticket.flow.equipment', $workshop))->assertOk()->assertSee('Sub Total')->assertSee('(+'.money(32).')')->assertSee(route('shop.product.show', $product));
        $other = \App\Models\Product::factory()->create(['status' => 'active']);
        $payload['equipment_quantities'] = [$other->id => 1];
        $this->post(route('workshop.ticket.flow.begin', $workshop), $payload)->assertSessionHasErrors('equipment');
        $this->assertSame(2, $workshop->tickets()->count());
    }

    public function test_optional_equipment_holds_last_twenty_minutes_in_countdown_availability_and_cleanup(): void
    {
        Queue::fake();
        $this->freezeTime();
        $service = app(\App\Services\WorkshopTicketService::class);
        $product = \App\Models\Product::factory()->create(['status' => 'active']);
        $equipment = $this->createTicketedWorkshop([
            'optional_product_ids' => [$product->id], 'max_tickets' => 1,
            'early_bird_price' => '8.00', 'early_bird_ticket_limit' => 1, 'early_bird_ends_at' => now()->addWeek(),
        ]);
        $ticketsOnly = $this->createTicketedWorkshop(['optional_product_ids' => [], 'max_tickets' => 1]);
        $buyer = ['quantity' => 1, 'firstname' => 'Jamie', 'surname' => 'Example', 'email' => 'hold@example.com', 'phone' => '0400123456'];
        $this->post(route('workshop.ticket.flow.begin', $equipment), $buyer)->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.equipment', $equipment))->assertOk()
            ->assertViewHas('session', fn ($session) => $session['expires_at'] === now()->addMinutes(20)->toIso8601String());
        $this->post(route('workshop.ticket.flow.begin', $ticketsOnly), $buyer)->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.payment', $ticketsOnly))->assertOk()
            ->assertViewHas('session', fn ($session) => $session['expires_at'] === now()->addMinutes(10)->toIso8601String());

        $this->travel(11)->minutes();
        $this->assertSame(0, $service->availableTickets($equipment));
        $this->assertSame(0, $equipment->earlyBirdTicketLimitRemaining());
        $this->assertSame(1, $service->availableTickets($ticketsOnly));
        $this->assertSame(0, $service->cleanupExpiredHolds($equipment));
        $this->assertSame(1, $service->cleanupExpiredHolds());
        $this->get(route('workshop.ticket.flow.equipment', $equipment))->assertOk();

        $this->travel(10)->minutes();
        $this->assertSame(1, $service->availableTickets($equipment));
        $this->assertSame(1, $equipment->earlyBirdTicketLimitRemaining());
        $this->assertSame(1, $service->cleanupExpiredHolds());
        $this->get(route('workshop.ticket.flow.equipment', $equipment))->assertRedirect(route('workshop.ticket.flow.start', $equipment));
    }

    public function test_equipment_and_delivery_steps_include_tickets_and_preserve_back_navigation(): void
    {
        Queue::fake();
        $product = \App\Models\Product::factory()->create(['status' => 'active', 'product_type' => 'physical', 'price' => 32, 'inventory_quantity' => 10]);
        $workshop = $this->createTicketedWorkshop(['optional_product_ids' => [$product->id], 'max_tickets' => 2]);
        $buyer = ['quantity' => 2, 'firstname' => 'Jamie', 'surname' => 'Example', 'email' => 'steps@example.com', 'phone' => '0400123456'];
        $this->get(route('workshop.ticket.flow.start', $workshop))->assertOk()->assertDontSee('Remaining:');
        $this->post(route('workshop.ticket.flow.begin', $workshop), $buyer)->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.start', $workshop))->assertOk()->assertSee('Remaining:')->assertSee('steps@example.com')->assertViewHas('ticketQuantity', 2);
        $this->post(route('workshop.ticket.flow.begin', $workshop), $buyer)->assertSessionHasNoErrors();
        $this->assertSame(2, $workshop->tickets()->count());
        $this->get(route('workshop.ticket.flow.equipment', $workshop))->assertOk()->assertSee('Remaining:')->assertSee('Tickets')->assertSee('Sub Total')
            ->assertDontSee('Update total')->assertDontSee('Continue without equipment')->assertDontSee('name="variants['.$product->id.']"', false);
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), ['action' => 'select', 'quantities' => [$product->id => 2]])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.delivery', $workshop));
        $this->get(route('workshop.ticket.flow.delivery', $workshop))->assertOk()->assertSee('Remaining:')->assertSee('Delivery details')->assertSee('Tickets')->assertViewHas('ticketAmount', 30.0);
        $delivery = ['shipping_method_code' => 'pickup'];
        $this->postJson(route('workshop.ticket.flow.delivery.save', $workshop), $delivery + ['action' => 'quote'])->assertOk()->assertJsonPath('summary.total', 64);
        $this->post(route('workshop.ticket.flow.delivery.save', $workshop), $delivery + ['action' => 'continue', 'confirmed_total' => 64])->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.payment', $workshop));
        $this->get(route('workshop.ticket.flow.payment', $workshop))->assertOk()->assertSee('Remaining:')->assertViewHas('totalAmount', 94.0);
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), ['action' => 'select', 'quantities' => [$product->id => 0]])
            ->assertRedirect(route('workshop.ticket.flow.payment', $workshop));
        $this->get(route('workshop.ticket.flow.payment', $workshop))->assertOk()->assertViewHas('totalAmount', 30.0);
    }

    public function test_completed_checkout_can_start_another_purchase_without_reusing_the_old_tickets(): void
    {
        Queue::fake();
        $workshop = $this->createTicketedWorkshop(['max_tickets' => 3]);
        $buyer = ['quantity' => 1, 'firstname' => 'Jamie', 'surname' => 'Example', 'email' => 'repeat@example.com', 'phone' => '0400123456'];
        $this->post(route('workshop.ticket.flow.begin', $workshop), $buyer)->assertRedirect(route('workshop.ticket.flow.payment', $workshop));
        $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => 'bank_transfer'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $firstTicket = $workshop->tickets()->sole();
        $firstInvoiceId = $firstTicket->invoice_id;

        // A paid purchase must still finish participant details before starting again.
        $this->get(route('workshop.ticket.flow.start', $workshop))->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $this->post(route('workshop.ticket.flow.begin', $workshop), $buyer)->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $this->assertSame(1, $workshop->tickets()->count());
        $this->post(route('workshop.ticket.flow.details.save', $workshop), ['tickets' => [
            ['id' => $firstTicket->id] + $buyer,
        ]])->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $workshop));
        $this->get(route('workshop.ticket.flow.details', $workshop))->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $this->travel(1)->hours();
        $this->get(route('workshop.ticket.flow.start', $workshop))->assertOk()->assertViewIs('workshop.tickets.start')
            ->assertViewHas('ticketQuantity', 1)->assertViewHas('availableTickets', 2);
        $this->get(route('workshop.ticket.flow.complete', $workshop))->assertOk();
        $this->post(route('workshop.ticket.flow.begin', $workshop), $buyer)->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.ticket.flow.payment', $workshop));
        $secondTicket = $workshop->tickets()->whereKeyNot($firstTicket->id)->sole();
        $this->assertSame(Ticket::STATUS_HOLD, (int) $secondTicket->status);
        $this->assertSame($firstInvoiceId, $firstTicket->fresh()->invoice_id);
        $this->assertSame(Ticket::STATUS_PENDING_XFER, (int) $firstTicket->fresh()->status);
        $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => 'bank_transfer'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $this->assertNotSame($firstInvoiceId, $secondTicket->fresh()->invoice_id);
        $this->assertDatabaseCount('invoices', 2);
        $this->get(route('workshop.ticket.flow.details', $workshop))->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->modelKeys() === [$secondTicket->id]);
    }

    public function test_equipment_shows_product_details_and_uses_store_shipment_grouping(): void
    {
        Queue::fake();
        $product = \App\Models\Product::factory()->create([
            'status' => 'active', 'product_type' => 'physical', 'price' => 32,
            'inventory_quantity' => 0, 'allow_backorder' => true,
            'short_description' => 'A small programmable board for the course.',
        ]);
        $workshop = $this->createTicketedWorkshop(['optional_product_ids' => [$product->id]]);
        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1, 'firstname' => 'Jamie', 'surname' => 'Example',
            'email' => 'details@example.com', 'phone' => '0400123456',
        ])->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.equipment', $workshop))->assertOk()
            ->assertSee($product->short_description)->assertSee($product->primaryImageUrl())
            ->assertSee('Available to order')->assertSee('More coming soon');
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), [
            'action' => 'select', 'quantities' => [$product->id => 1],
        ])->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.delivery', $workshop))->assertOk()
            ->assertSee('Shipping address')->assertDontSee('Billing address, also used')
            ->assertSee('shipment.title_primary || shipment.title', false)
            ->assertViewHas('summary', fn ($summary) => ! $summary['shipping_quote']['offers_consolidation']);
    }

    public static function equipmentPaymentMethods(): array
    {
        return [['bank_transfer'], ['pay_at_door'], ['credit_card'], ['credit_card_fallback'], ['credit_card_credit'], ['credit'], ['declined'], ['price_changed'], ['stock_changed']];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('equipmentPaymentMethods')]
    public function test_optional_equipment_shares_one_checkout_invoice_using_existing_pickup(string $method): void
    {
        Queue::fake();
        config(['mail.admin_bcc' => 'ops@example.com']);
        $creditAmount = $method === 'credit_card_credit' ? 20.0 : ($method === 'credit' ? 40.0 : 0.0);
        $useFallback = $method === 'credit_card_fallback';
        if ($useFallback || $method === 'credit_card_credit') {
            $method = 'credit_card';
        }
        if ($creditAmount > 0) {
            $buyer = User::factory()->create(['email' => 'equipment@example.com']);
            $this->actingAs($buyer);
            Payment::factory()->create(['user_id' => $buyer->id, 'payment_method' => Payment::PAYMENT_METHOD_CREDIT, 'total_amount' => $creditAmount, 'gst_amount' => 0]);
        }
        $product = \App\Models\Product::factory()->create(['status' => 'active', 'product_type' => 'physical', 'price' => 25, 'inventory_quantity' => 10]);
        $workshop = $this->createTicketedWorkshop(['optional_product_ids' => [$product->id]]);
        $regularCart = app(\App\Services\StoreCartService::class);
        $regularCart->add($product, null, 3);
        $this->post(route('workshop.ticket.flow.begin', $workshop), ['quantity' => 1, 'firstname' => 'Jamie', 'surname' => 'Example', 'email' => 'equipment@example.com', 'phone' => '0400123456'])
            ->assertRedirect(route('workshop.ticket.flow.equipment', $workshop));
        $this->get(route('workshop.ticket.flow.equipment', $workshop))->assertOk();
        $data = ['quantities' => [$product->id => 1], 'shipping_method_code' => 'pickup', 'billing_address' => '12 Test Street', 'billing_city' => 'Brisbane', 'billing_state' => 'QLD', 'billing_postcode' => '4000'];
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), $data + ['action' => 'review'])->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.equipment', $workshop))->assertOk()->assertSee('Sub Total');
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), $data + ['action' => 'continue', 'confirmed_total' => 25])->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.payment', $workshop));
        $this->travel(11)->minutes();
        $this->get(route('workshop.ticket.flow.payment', $workshop))->assertOk()->assertSee('Equipment')->assertSee('Delivery')->assertDontSee('Equipment &amp; delivery', false);
        if (in_array($method, ['price_changed', 'stock_changed'], true)) {
            $product->update($method === 'price_changed' ? ['price' => 30] : ['inventory_quantity' => 0]);
            $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => 'credit_card'])->assertSessionHasErrors('equipment');
            $this->assertDatabaseCount('store_orders', 0);
            $this->assertDatabaseCount('invoices', 0);
            return;
        }
        if (in_array($method, ['bank_transfer', 'pay_at_door'], true)) {
            $this->get(route('workshop.ticket.flow.payment', $workshop))->assertOk()
                ->assertDontSee('Equipment &amp; delivery', false)->assertSee('Delivery')
                ->assertDontSee('<option value="bank_transfer">', false)->assertDontSee('<option value="pay_at_door">', false);
            $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => $method])->assertSessionHasErrors('payment_method');
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('store_orders', 0);
            return;
        }
        if (in_array($method, ['credit_card', 'declined'], true)) {
            config(['services.square.location_id' => 'TEST']);
            $gateway = Mockery::mock(SquareApiService::class);
            $gateway->shouldReceive('isEnabled')->andReturn(true);
            $charge = $gateway->shouldReceive('createPayment')->once()->with(Mockery::on(function ($payload) use ($creditAmount) {
                Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job) => $job->mailable instanceof StoreOrderConfirmation
                    || $job->mailable instanceof StoreOrderPaid
                    || $job->mailable instanceof StoreOrderAdminNotification);

                return $payload['amount_money']['amount'] === (int) ((40 - $creditAmount) * 100);
            }));
            if ($method === 'declined') {
                $charge->andThrow(new \RuntimeException('Declined'));
                $gateway->shouldReceive('userFacingPaymentErrorMessage')->andReturn('Card declined');
            } else {
                $charge->andReturn(['payment' => ['id' => 'equipment-payment', 'status' => 'COMPLETED', 'amount_money' => ['amount' => (int) ((40 - $creditAmount) * 100)]]]);
            }
            $this->app->instance(SquareApiService::class, $gateway);
        }
        $response = $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => $method === 'declined' ? 'credit_card' : $method, 'source_id' => 'test-token', 'apply_account_credit' => $creditAmount > 0]);
        if ($method === 'declined') {
            $response->assertSessionHasErrors('payment_method');
            Queue::assertNotPushed(SendEmail::class);
            Queue::assertNotPushed(SendDeferredStoreOrderEmail::class);
            $this->assertDatabaseCount('store_orders', 0);
            $this->assertDatabaseCount('payments', 0);
            $this->assertDatabaseCount('invoices', 0);
            $this->assertSame(10, $product->fresh()->inventory_quantity);
            return;
        }
        $response->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $order = \App\Models\StoreOrder::firstOrFail();
        $ticket = Ticket::where('workshop_id', $workshop->id)->firstOrFail();
        $this->assertEquals($ticket->invoice_id, $order->invoice_id);
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(['ticket', 'product'], $ticket->invoice->lines()->orderBy('line_number')->pluck('kind')->all());
        $this->assertSame('25.00', $order->total_amount);
        $this->assertSame('40.00', $ticket->invoice->total_amount);
        $this->assertSame('12 Test Street', $ticket->invoice->billing_address);
        $this->assertSame('pickup', $order->shipping_method_code);
        if ($creditAmount > 0) {
            $this->assertSame($creditAmount, (float) $order->invoice->allocations()->whereHas('customerPayment', fn ($query) => $query->where('payment_method', Payment::PAYMENT_METHOD_CREDIT))->sum('allocated_amount'));
            $this->assertTrue($order->isPaid());
        }
        if ($method === 'credit_card') {
            $payment = Payment::where('payment_method', 'credit_card')->sole();
            $this->assertSame(40 - $creditAmount, (float) $payment->total_amount);
            $this->assertStringContainsString($order->order_number, $payment->reference);
            $this->assertStringContainsString($ticket->reference_code, $payment->reference);
            $this->assertEqualsCanonicalizing([40 - $creditAmount], $payment->allocations()->pluck('allocated_amount')->map(fn ($amount) => (float) $amount)->all());
            $this->assertSame(0.0, (float) $order->invoice->outstandingAmount());
            $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => 'credit_card', 'source_id' => 'test-token'])->assertRedirect(route('workshop.ticket.flow.details', $workshop));
            $this->assertDatabaseCount('payments', $creditAmount > 0 ? 2 : 1);
            $this->assertDatabaseCount('store_orders', 1);
            Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job) => $job->mailable instanceof StoreOrderConfirmation);
            Queue::assertNotPushed(SendDeferredStoreOrderEmail::class);
            Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job) => $job->to === 'equipment@example.com');
            $delivery = WorkshopTicketEmail::sole();
            $this->assertSame($order->id, $delivery->equipment_order_id);
            $this->assertSame(40.0, (float) $delivery->amount);
            $scheduledJob = Queue::pushed(SendWorkshopTicketOrderEmail::class)->first();
            if ($useFallback) {
                $this->assertSame(3, app(\App\Services\StoreCartService::class)->lines()->sum('quantity'));
                $this->flushSession();
                $this->travel(30)->minutes();
                $scheduledJob->handle(app(\App\Services\WorkshopTicketOrderEmailService::class));
            } else {
                $this->post(route('workshop.ticket.flow.details.save', $workshop), ['tickets' => [[
                    'id' => $ticket->id,
                    'firstname' => 'Jamie',
                    'surname' => 'Example',
                    'email' => 'equipment@example.com',
                    'phone' => '0400123456',
                ]]])->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $workshop));
                $this->get(route('workshop.ticket.flow.complete', $workshop))->assertOk()->assertSee($order->order_number);
            }
            // The fallback must not send a second confirmation after details are submitted.
            $scheduledJob->handle(app(\App\Services\WorkshopTicketOrderEmailService::class));
            Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job) => $job->mailable instanceof StoreOrderPaid);
            $customerEmails = Queue::pushed(SendEmail::class, fn (SendEmail $job) => $job->to === 'equipment@example.com');
            $this->assertCount(1, $customerEmails);
            $mail = $customerEmails->first()->mailable;
            $this->assertInstanceOf(TicketOrderConfirmation::class, $mail);
            $this->assertSame(1, $mail->ticketAttachmentCount);
            $this->assertSame(1, $mail->receiptAttachmentCount);
            $this->assertSame(1, $mail->invoiceAttachmentCount);
            $this->assertSame(40.0, $mail->amount);
            $this->assertSame(40 - $creditAmount, $mail->paymentAmount);
            $this->assertSame($creditAmount, $mail->creditAppliedAmount);
            $this->assertSame($order->order_number, $mail->equipmentOrder['number']);
            $html = $mail->render();
            $this->assertStringContainsString($order->order_number, $html);
            $this->assertStringNotContainsString(e($product->title), $html);
            $this->assertStringContainsString('View Store Order', $html);
            $this->assertCount($creditAmount > 0 ? 4 : 3, $mail->rawAttachments);
            $this->assertNotNull($order->fresh()->order_paid_emailed_at);
            Queue::assertPushed(SendEmail::class, fn (SendEmail $job) => $job->to === 'ops@example.com'
                && $job->mailable instanceof StoreOrderAdminNotification
                && $job->mailable->notificationType === 'paid'
                && $job->mailable->order->isPaid());
        }

        if (! $useFallback) {
            $this->assertSame(3, app(\App\Services\StoreCartService::class)->lines()->sum('quantity'));
        }
    }

    public function test_equipment_manual_shipping_quote_does_not_charge_equipment_with_tickets(): void
    {
        Queue::fake();
        $product = \App\Models\Product::factory()->create(['status' => 'active', 'product_type' => 'physical', 'price' => 25, 'inventory_quantity' => 10, 'shipping_units' => 0.0, 'min_satchel_rank' => 1]);
        $workshop = $this->createTicketedWorkshop(['optional_product_ids' => [$product->id]]);
        $this->post(route('workshop.ticket.flow.begin', $workshop), ['quantity' => 1, 'firstname' => 'Jamie', 'surname' => 'Example', 'email' => 'quote-equipment@example.com', 'phone' => '0400123456']);
        $data = ['quantities' => [$product->id => 1], 'shipping_method_code' => 'request_quote', 'billing_address' => '12 Test Street', 'billing_city' => 'Brisbane', 'billing_state' => 'QLD', 'billing_postcode' => '4000'];
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), $data + ['action' => 'review'])->assertSessionHasNoErrors();
        $this->post(route('workshop.ticket.flow.delivery.save', $workshop), [
            'shipping_method_code' => 'request_quote', 'action' => 'continue', 'confirmed_total' => 0,
        ])->assertSessionHasErrors(['billing_address', 'billing_city', 'billing_state', 'billing_postcode']);
        $this->post(route('workshop.ticket.flow.equipment.save', $workshop), $data + ['action' => 'continue', 'confirmed_total' => 0])->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.payment', $workshop))->assertOk()->assertSee('not charged now');
        $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => 'bank_transfer'])->assertSessionHasErrors('payment_method');
        config(['services.square.location_id' => 'TEST']);
        $gateway = Mockery::mock(SquareApiService::class);
        $gateway->shouldReceive('isEnabled')->andReturn(true);
        $gateway->shouldReceive('createPayment')->once()->with(Mockery::on(fn ($payload) => $payload['amount_money']['amount'] === 1500))->andReturn(['payment' => ['id' => 'quote-ticket-payment', 'status' => 'COMPLETED', 'amount_money' => ['amount' => 1500]]]);
        $this->app->instance(SquareApiService::class, $gateway);
        $this->post(route('workshop.ticket.flow.payment.process', $workshop), ['payment_method' => 'credit_card', 'source_id' => 'test-token'])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('quotes', 1);
        $this->assertDatabaseCount('store_orders', 0);
        $this->assertSame('15.00', Invoice::firstOrFail()->total_amount);
    }

    public function test_ticket_checkout_allocates_early_bird_pricing_only_up_to_the_configured_limit(): void
    {
        Queue::fake();

        $workshop = $this->createTicketedWorkshop([
            'price' => '10.00',
            'early_bird_price' => '8.00',
            'early_bird_ticket_limit' => 2,
            'early_bird_ends_at' => now()->addWeek(),
        ]);

        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 3,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->assertSame(
            [true, true, false],
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Ticket $ticket): bool => $ticket->isEarlyBirdTicket())
                ->all()
        );
        $this->assertFalse($workshop->fresh()->earlyBirdIsActive());

        $this->get(route('workshop.show', $workshop))
            ->assertOk()
            ->assertDontSee('Early bird sold out', false);

        $this->get(route('workshop.ticket.flow.payment', $workshop))
            ->assertOk()
            ->assertSee('Early Bird', false)
            ->assertSee('2 @ $8.00 per ticket (Early bird)', false)
            ->assertSee('Tickets', false)
            ->assertSee('1 @ $10.00 per ticket', false)
            ->assertSee('$26.00', false);

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'pay_at_door',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $this->get(route('workshop.ticket.flow.details', $workshop))
            ->assertOk()
            ->assertSee('Save $4.00 with earlybird pricing.', false);

        $invoice = Invoice::query()->sole();
        $lines = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->orderBy('line_number')
            ->get();

        $this->assertSame(26.00, (float) $invoice->total_amount);
        $this->assertSame([8.00, 8.00, 10.00], $lines->map(fn (InvoiceLine $line): float => (float) $line->line_total_inc_tax)->all());
        $this->assertStringContainsString('Early Bird ticket.', (string) $lines[0]->notes);
        $this->assertStringContainsString('Early Bird ticket.', (string) $lines[1]->notes);
        $this->assertStringNotContainsString('Early Bird ticket.', (string) $lines[2]->notes);
        $this->assertSame(
            [true, true, false],
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Ticket $ticket): bool => $ticket->isEarlyBirdTicket())
                ->all()
        );
        $this->assertSame(
            [Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_DOOR, Ticket::STATUS_PENDING_DOOR],
            Ticket::query()
                ->where('workshop_id', $workshop->id)
                ->orderBy('id')
                ->get()
                ->map(fn (Ticket $ticket): int => (int) $ticket->status)
                ->all()
        );
    }

    public function test_ticket_checkout_start_page_shows_the_remaining_early_bird_slots_in_places(): void
    {
        $workshop = $this->createTicketedWorkshop([
            'max_tickets' => 6,
            'price' => '10.00',
            'early_bird_price' => '7.00',
            'early_bird_ticket_limit' => 1,
            'early_bird_ends_at' => now()->addWeek(),
        ]);

        $this->get(route('workshop.ticket.flow.start', $workshop))
            ->assertOk()
            ->assertSee('Places', false)
            ->assertSeeHtml('6 <span class="text-gray-500">(1 early bird ticket remains)</span>');
    }

    public function test_paid_ticket_checkout_sends_the_combined_email_to_the_purchaser_and_the_ticket_email_to_a_different_holder(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1500
                && str_contains($idempotencyKey, '-amt-1500')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1500],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'credit_card',
            'source_id' => 'cnon:card-nonce-ok',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->post(route('workshop.ticket.flow.details.save', $workshop), [
            'tickets' => [
                [
                    'id' => $ticket->id,
                    'firstname' => 'Ticket',
                    'surname' => 'Holder',
                    'email' => 'holder@example.com',
                    'phone' => '0400123456',
                ],
            ],
        ])->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $delivery = WorkshopTicketEmail::query()->sole();
        $this->assertSame(WorkshopTicketEmail::STATUS_QUEUED, $delivery->status);
        $this->assertNotNull($delivery->queued_at);
        $this->assertSame('buyer@example.com', $delivery->recipient_email);

        Queue::assertPushed(SendWorkshopTicketOrderEmail::class, function (SendWorkshopTicketOrderEmail $job) use ($delivery): bool {
            return $job->workshopTicketEmailId === $delivery->id;
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $mailable = $job->mailable;
            if (! $mailable instanceof TicketOrderConfirmation) {
                return false;
            }

            /** @var TicketOrderConfirmation $mailable */
            $mailable->build();

            return $job->to === 'buyer@example.com'
                && $mailable->hasInvoiceAttachment
                && $mailable->hasReceiptAttachment
                && $mailable->ticketAttachmentCount === 1
                && $this->mailableSubject($mailable) === 'Your ticket and receipt for Ticket Email Workshop';
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $mailable = $job->mailable;
            if (! $mailable instanceof TicketAttendeeUpdate) {
                return false;
            }

            /** @var TicketAttendeeUpdate $mailable */
            $mailable->build();

            return $job->to === 'holder@example.com'
                && $mailable->mode === 'new_holder'
                && $mailable->recipientName === 'Ticket Holder'
                && $mailable->purchaserName === 'Jamie Example'
                && $this->mailableSubject($mailable) === "You're in! Your workshop ticket for Ticket Email Workshop";
        });
    }

    public function test_ticket_checkout_can_apply_a_voucher_and_persists_the_discount_in_the_invoice(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1000
                && str_contains($idempotencyKey, '-amt-1000')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-2',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:2',
                'order_id' => 'sq-order-2',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1000],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        Coupon::factory()->create([
            'code' => 'SAVE5',
            'description' => 'Ticket voucher',
            'status' => Coupon::STATUS_ACTIVE,
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'amount' => 5.00,
        ]);

        Coupon::factory()->create([
            'code' => 'PRODUCTONLY',
            'description' => 'Product voucher',
            'status' => Coupon::STATUS_ACTIVE,
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'amount' => 5.00,
            'applies_to_products' => true,
            'applies_to_workshops' => false,
        ]);

        $restrictedWorkshop = $this->createTicketedWorkshop([
            'price' => '15.00',
            'title' => 'Restricted Ticket Workshop',
        ]);

        $workshopOnlyCoupon = Coupon::factory()->create([
            'code' => 'WORKSHOPONLY',
            'description' => 'Workshop voucher',
            'status' => Coupon::STATUS_ACTIVE,
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'amount' => 5.00,
            'applies_to_products' => false,
            'applies_to_workshops' => true,
        ]);
        $workshopOnlyCoupon->restrictedWorkshops()->attach($restrictedWorkshop);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $invalidVoucherResponse = $this->postJson(route('workshop.ticket.flow.voucher', $workshop), [
            'voucher_code' => 'NOPE',
        ]);
        $invalidVoucherResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('voucher_code');

        $productOnlyVoucherResponse = $this->postJson(route('workshop.ticket.flow.voucher', $workshop), [
            'voucher_code' => 'PRODUCTONLY',
        ]);
        $productOnlyVoucherResponse
            ->assertStatus(422)
            ->assertJsonPath('message', 'That voucher cannot be used for workshop tickets.')
            ->assertJsonValidationErrors('voucher_code');

        $restrictedWorkshopVoucherResponse = $this->postJson(route('workshop.ticket.flow.voucher', $workshop), [
            'voucher_code' => 'WORKSHOPONLY',
        ]);
        $restrictedWorkshopVoucherResponse
            ->assertStatus(422)
            ->assertJsonPath('message', 'That voucher cannot be used for this workshop.')
            ->assertJsonValidationErrors('voucher_code');

        $validVoucherResponse = $this->postJson(route('workshop.ticket.flow.voucher', $workshop), [
            'voucher_code' => 'SAVE5',
        ]);
        $validVoucherResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.voucher_code', 'SAVE5')
            ->assertJsonPath('summary.voucher_discount_amount', 5);

        $this->get(route('workshop.ticket.flow.payment', $workshop))
            ->assertOk()
            ->assertSee('$-5.00 (SAVE5)', false)
            ->assertSee('Change voucher', false);

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'credit_card',
            'source_id' => 'cnon:card-nonce-ok',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->post(route('workshop.ticket.flow.details.save', $workshop), [
            'tickets' => [
                [
                    'id' => $ticket->id,
                    'firstname' => 'Ticket',
                    'surname' => 'Holder',
                    'email' => 'holder@example.com',
                    'phone' => '0400123456',
                ],
            ],
        ])->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $invoice = Invoice::query()->sole();
        $discountLine = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('kind', 'discount')
            ->sole();
        $cardPayment = Payment::query()
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT_CARD)
            ->sole();

        $this->assertSame(10.00, (float) $invoice->total_amount);
        $this->assertSame('Voucher SAVE5', (string) $discountLine->description);
        $this->assertSame(-5.00, (float) $discountLine->line_total_inc_tax);
        $this->assertSame(10.00, (float) $cardPayment->total_amount);
    }

    public function test_account_terms_ticket_checkout_uses_the_users_terms_for_the_invoice_due_date(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 1)->setTime(10, 0));

        $buyer = User::factory()->create([
            'firstname' => 'Terms',
            'surname' => 'Buyer',
            'email' => 'terms-buyer@example.com',
            'account_terms_days' => 14,
        ]);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->actingAs($buyer);

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Terms',
            'surname' => 'Buyer',
            'email' => 'terms-buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'account_terms',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();
        $invoice = Invoice::query()->sole();

        $this->assertSame(Ticket::STATUS_ACCOUNT, (int) $ticket->status);
        $this->assertSame('2026-04-15', optional($invoice->due_date)->toDateString());
    }

    public function test_logged_in_ticket_checkout_uses_account_credit_before_charging_the_remaining_card_amount(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $buyer = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer-credit@example.com',
        ]);

        Payment::factory()->create([
            'user_id' => $buyer->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 5.00,
            'gst_amount' => 0,
            'reference' => 'Account credit grant',
        ]);

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1000
                && str_contains($idempotencyKey, '-amt-1000')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1000],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->actingAs($buyer);
        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer-credit@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->get(route('workshop.ticket.flow.payment', $workshop))
            ->assertOk()
            ->assertSee('Apply account credit first', false)
            ->assertSee('Available credit:', false)
            ->assertSee('Remaining after credit:', false);

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'credit_card',
            'source_id' => 'cnon:card-nonce-ok',
            'apply_account_credit' => '1',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->post(route('workshop.ticket.flow.details.save', $workshop), [
            'tickets' => [
                [
                    'id' => $ticket->id,
                    'firstname' => 'Jamie',
                    'surname' => 'Example',
                    'email' => 'buyer-credit@example.com',
                    'phone' => '0400123456',
                ],
            ],
        ])->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $creditPayment = Payment::query()
            ->where('user_id', $buyer->id)
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT)
            ->sole();
        $cardPayment = Payment::query()
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT_CARD)
            ->sole();

        $this->assertSame(5.00, (float) InvoicePaymentAllocation::query()->where('payment_id', $creditPayment->id)->sum('allocated_amount'));
        $this->assertSame(10.00, (float) $cardPayment->total_amount);
        $this->assertSame(10.00, (float) InvoicePaymentAllocation::query()->where('payment_id', $cardPayment->id)->sum('allocated_amount'));

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $mailable = $job->mailable;
            if (! $mailable instanceof TicketOrderConfirmation) {
                return false;
            }

            $mailable->build();

            return $job->to === 'buyer-credit@example.com'
                && $mailable->hasInvoiceAttachment
                && $mailable->hasReceiptAttachment
                && $mailable->hasCreditReceiptAttachment
                && $mailable->receiptAttachmentCount === 1
                && $mailable->creditReceiptAttachmentCount === 1
                && $mailable->ticketAttachmentCount === 1
                && $mailable->paymentMethodLabel === 'Account Credit + Credit Card'
                && $mailable->creditAppliedAmount === 5.00
                && $mailable->paymentAmount === 10.00
                && is_string($mailable->creditReferenceSummary)
                && $mailable->creditReferenceSummary !== ''
                && $this->mailableSubject($mailable) === 'Your ticket and receipts for Ticket Email Workshop';
        });
    }

    public function test_missing_ticket_details_session_redirects_back_to_the_workshop_page_with_a_toast_message(): void
    {
        $workshop = $this->createTicketedWorkshop();

        $response = $this->post(route('workshop.ticket.flow.details.save', $workshop), []);

        $response->assertRedirect(route('workshop.show', $workshop));
        $response->assertSessionHas('message-title', 'Session expired');
        $response->assertSessionHas('message-type', 'warning');
        $response->assertSessionHas(
            'message',
            'Your checkout session expired while this page was open. Reload this page or restart checkout before saving ticket details.'
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicketedWorkshop(array $overrides = []): Workshop
    {
        $author = User::factory()->create();
        $location = Location::factory()->create();
        /** @var Media $hero */
        $hero = Media::factory()->create([
            'name' => 'hero-'.strtolower((string) fake()->unique()->bothify('######')).'.png',
            'mime_type' => 'image/png',
            'user_id' => (string) $author->id,
        ]);
        $startsAt = now()->addDays(7);

        return Workshop::query()->create(array_merge([
            'title' => 'Ticket Email Workshop',
            'content' => '<p>Hands-on session.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHour(),
            'status' => 'open',
            'price' => '15.00',
            'ages' => '8+',
            'registration' => 'tickets',
            'registration_data' => null,
            'private_code' => null,
            'is_private' => false,
            'is_hidden' => false,
            'max_tickets' => 20,
            'ticket_group_slug' => null,
            'location_id' => (string) $location->id,
            'user_id' => (string) $author->id,
            'hero_media_name' => (string) $hero->name,
        ], $overrides));
    }

    private function mailableSubject(object $mailable): string
    {
        $reflection = new \ReflectionClass($mailable);
        $property = $reflection->getProperty('subject');
        $property->setAccessible(true);

        return (string) $property->getValue($mailable);
    }
}
