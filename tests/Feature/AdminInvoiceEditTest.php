<?php

namespace Tests\Feature;

use App\Models\Invoice;
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
