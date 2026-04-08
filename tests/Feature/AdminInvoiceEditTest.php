<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\TaxAdjustment;
use App\Models\Ticket;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemTracking;
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

        $payment = \App\Models\Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'kind' => \App\Models\Payment::KIND_PAYMENT,
            'payment_method' => \App\Models\Payment::PAYMENT_METHOD_CASH,
            'total_amount' => 27.50,
            'gst_amount' => 2.50,
        ]);

        \App\Models\InvoicePaymentAllocation::query()->create([
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
