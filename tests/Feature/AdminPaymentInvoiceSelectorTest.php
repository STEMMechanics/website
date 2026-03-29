<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPaymentInvoiceSelectorTest extends TestCase
{
    use RefreshDatabase;

    public function test_payment_create_page_shows_all_actionable_invoices_when_no_customer_is_selected(): void
    {
        $admin = $this->createAdminUser();

        $firstCustomer = User::factory()->create([
            'firstname' => 'Jane',
            'surname' => 'Doe',
            'email' => 'jane.doe@example.com',
        ]);
        $secondCustomer = User::factory()->create([
            'firstname' => 'Robin',
            'surname' => 'Smith',
            'email' => 'robin.smith@example.com',
        ]);

        $firstInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-100001',
            'user_id' => $firstCustomer->id,
            'billing_name' => 'Jane Doe',
            'billing_email' => 'jane.doe@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110.00,
            'subtotal_amount' => 100.00,
            'gst_amount' => 10.00,
        ]);

        $secondInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-100002',
            'user_id' => $secondCustomer->id,
            'billing_name' => 'Robin Smith',
            'billing_email' => 'robin.smith@example.com',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 45.00,
            'subtotal_amount' => 40.91,
            'gst_amount' => 4.09,
        ]);

        $partialInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-100004',
            'user_id' => $secondCustomer->id,
            'billing_name' => 'Robin Smith',
            'billing_email' => 'robin.smith@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);

        $partialPayment = Payment::factory()->create([
            'user_id' => $secondCustomer->id,
            'created_by' => $admin->id,
            'total_amount' => 30.00,
            'gst_amount' => 0.00,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $partialPayment->id,
            'invoice_id' => $partialInvoice->id,
            'allocated_amount' => 30.00,
        ]);

        $draftInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-100003',
            'user_id' => $secondCustomer->id,
            'billing_name' => 'Robin Smith',
            'billing_email' => 'robin.smith@example.com',
            'status' => Invoice::STATUS_DRAFT,
            'total_amount' => 60.00,
            'subtotal_amount' => 54.55,
            'gst_amount' => 5.45,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payment.create'));

        $response->assertOk();
        $response->assertSee('Select invoice', false);
        $response->assertSee('Search invoices', false);
        $response->assertSee('Add all', false);
        $response->assertSee('INV-100001', false);
        $response->assertSee('Jane Doe', false);
        $response->assertSee('INV-100002', false);
        $response->assertSee('Robin Smith', false);
        $response->assertSee('Partially paid', false);
        $response->assertDontSee('INV-100003', false);
        $response->assertDontSee('Filter Invoices', false);
        $response->assertDontSee('Invoice scope', false);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
