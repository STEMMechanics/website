<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemTracking;
use App\Models\TaxAdjustment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminInvoiceEditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_invoice_edit_shows_zero_remaining_for_cancelled_invoice(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_CANCELLED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSeeText('Cancelled')
            ->assertSeeText('Due (after adjustments): $0.00')
            ->assertSee('Remaining:</strong> $0.00', false);
    }

    public function test_admin_can_write_off_ticket_invoice_without_cancelling_ticket(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'ticket',
            'description' => 'Workshop Ticket',
            'quantity' => 1,
            'unit_price_ex_tax' => 25.00,
            'line_total_ex_tax' => 25.00,
            'tax_amount' => 2.50,
            'line_total_inc_tax' => 27.50,
        ]);
        $ticket = Ticket::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => Ticket::STATUS_DONE,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.invoice.write-off', $invoice), [
                'reason' => 'Customer attended, but payment will not be recovered.',
            ])
            ->assertRedirect(route('admin.invoice.edit', $invoice))
            ->assertSessionHas('message', 'Invoice has been written off.');

        $invoice->refresh();
        $ticket->refresh();

        $this->assertSame(Invoice::STATUS_WRITTEN_OFF, (string) $invoice->status);
        $this->assertSame('Customer attended, but payment will not be recovered.', (string) $invoice->written_off_reason);
        $this->assertNotNull($invoice->written_off_at);
        $this->assertSame(0.0, (float) $invoice->displayOutstandingAmount());
        $this->assertSame(Ticket::STATUS_DONE, (int) $ticket->status);
        $this->assertSame((string) $invoice->id, (string) $ticket->invoice_id);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSeeText('Written off')
            ->assertSeeText('Customer attended, but payment will not be recovered.')
            ->assertSeeText('Due (after adjustments): $0.00')
            ->assertSee('Remaining:</strong> $0.00', false)
            ->assertDontSeeText('Copy Payment Link')
            ->assertDontSeeText('Record Payment');
    }

    public function test_admin_invoice_write_off_requires_a_reason(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.write-off', $invoice), [
                'reason' => '',
            ])
            ->assertRedirect(route('admin.invoice.edit', $invoice))
            ->assertSessionHasErrors('reason');

        $this->assertSame(Invoice::STATUS_ISSUED, (string) $invoice->fresh()->status);
        $this->assertNull($invoice->fresh()->written_off_at);
    }

    public function test_written_off_invoice_cannot_generate_payment_link_or_accept_public_payment(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_WRITTEN_OFF,
            'written_off_at' => now(),
            'written_off_reason' => 'Uncollectable workshop ticket.',
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.invoice.payment-link', $invoice))
            ->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'This invoice has been written off.',
            ]);

        $this->get(route('invoice.public.pay.show', $invoice))
            ->assertNotFound();
    }

    public function test_admin_invoice_edit_disables_cancel_action_when_payments_are_allocated(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
            'total_amount' => 27.50,
            'gst_amount' => 2.50,
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 27.50,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('title="Reverse/refund allocated payments before cancellation."', false)
            ->assertSee('disabled', false);
    }

    public function test_admin_invoice_edit_disables_cancel_action_when_linked_ticket_exists(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        Ticket::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => Ticket::STATUS_PENDING_DOOR,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('title="This invoice has linked tickets. Cancel the ticket instead; it creates the tax adjustment note and settles the invoice."', false)
            ->assertSee('disabled', false);

        $this->actingAs($admin)
            ->delete(route('admin.invoice.destroy', $invoice))
            ->assertRedirect(route('admin.invoice.edit', $invoice))
            ->assertSessionHas('message', 'This invoice has linked tickets. Cancel the ticket instead; it creates the tax adjustment note and settles the invoice.');

        $this->assertSame(Invoice::STATUS_ISSUED, (string) $invoice->fresh()->status);
    }

    public function test_admin_invoice_edit_disables_cancel_action_when_linked_store_order_exists(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'inventory_quantity' => 5,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'paid_at' => now(),
        ]);
        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
            'delayed_quantity' => 0,
        ]);
        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'parcel_number' => 1,
            'dispatched_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('title="This invoice has linked store orders. Cancel the store order instead of the invoice."', false)
            ->assertSee('disabled', false);
    }

    public function test_admin_invoice_edit_disables_cancel_action_when_linked_store_order_is_ready_for_pickup(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $product = Product::factory()->create([
            'inventory_quantity' => 4,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
            'contains_physical' => true,
            'paid_at' => null,
        ]);
        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 1,
            'cancelled_available_quantity' => 0,
            'cancelled_delayed_quantity' => 0,
            'delayed_quantity' => 0,
        ]);

        $this->actingAs($admin)
            ->delete(route('admin.invoice.destroy', $invoice))
            ->assertRedirect(route('admin.invoice.edit', $invoice))
            ->assertSessionHas('message', 'This invoice has linked store orders. Cancel the store order instead of the invoice.');

        $this->assertSame(Invoice::STATUS_ISSUED, (string) $invoice->fresh()->status);
        $this->assertSame(StoreOrder::STATUS_READY_FOR_PICKUP, (string) $order->fresh()->status);
        $this->assertSame(4, (int) $product->fresh()->inventory_quantity);
        $this->assertSame(1, (int) $order->fresh('items')->items->first()->inventory_reserved_quantity);
    }

    public function test_admin_invoice_edit_renders_line_item_notes_as_a_full_width_row(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'ticket',
            'description' => 'Pinball Machines - Ticket WUF9XZ',
            'notes' => "Workshop date/time: Saturday, 20 Jun 2026 from 10:30 am to 11:30 am\nWorkshop location: Herberton Library",
            'quantity' => 1,
            'unit_price_ex_tax' => 4.55,
            'line_total_ex_tax' => 4.55,
            'tax_amount' => 0.45,
            'line_total_inc_tax' => 5.00,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('Line Item Notes')
            ->assertSee('md:col-span-12', false);
    }

    public function test_admin_invoice_edit_disables_cancel_action_when_tax_adjustment_exists(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 27.50,
            'subtotal_amount' => 25.00,
            'gst_amount' => 2.50,
        ]);

        TaxAdjustment::factory()->create([
            'invoice_id' => $invoice->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('title="This invoice already has tax adjustment notes. Reverse the adjustment instead of cancelling the invoice."', false)
            ->assertSee('disabled', false);

        $this->actingAs($admin)
            ->delete(route('admin.invoice.destroy', $invoice))
            ->assertRedirect(route('admin.invoice.edit', $invoice))
            ->assertSessionHas('message', 'This invoice already has tax adjustment notes. Reverse the adjustment instead of cancelling the invoice.');

        $this->assertSame(Invoice::STATUS_ISSUED, (string) $invoice->fresh()->status);
    }

    public function test_admin_invoice_create_renders_without_an_existing_invoice(): void
    {
        $admin = $this->createAdminUser();

        $this->actingAs($admin)
            ->get(route('admin.invoice.create'))
            ->assertOk()
            ->assertSeeText('Create Invoice')
            ->assertSeeText('Invoice Number')
            ->assertSeeText('Linked Quote')
            ->assertSeeText('Line Items');
    }

    public function test_admin_invoice_edit_renders_the_invoice_email_modal_fields(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Parker',
            'surname' => 'Lee',
            'email' => 'parker.lee@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_email' => 'parker.lee@example.com',
            'status' => Invoice::STATUS_SENT,
            'issue_date' => now()->subDays(3)->toDateString(),
            'due_date' => now()->addDays(11)->toDateString(),
            'total_amount' => 132.00,
            'subtotal_amount' => 120.00,
            'gst_amount' => 12.00,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSeeText('Subject')
            ->assertSeeText('CC Email')
            ->assertSeeText('purchase order number')
            ->assertSeeText('View and Pay Invoice button')
            ->assertSee('data-bwignore="true"', false);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
