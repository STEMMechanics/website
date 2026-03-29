<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Mail\PaymentReceiptPdf;
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
        $response->assertSee('Email receipt to customer', false);
        $response->assertSee('INV-100001', false);
        $response->assertSee('Jane Doe', false);
        $response->assertSee('INV-100002', false);
        $response->assertSee('Robin Smith', false);
        $response->assertSee('Partially paid', false);
        $response->assertDontSee('INV-100003', false);
        $response->assertDontSee('Filter Invoices', false);
        $response->assertDontSee('Invoice scope', false);
    }

    public function test_payment_edit_page_keeps_previous_allocation_labels_even_when_the_invoice_is_now_paid(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Taylor',
            'surname' => 'Jones',
            'email' => 'taylor.jones@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-200001',
            'user_id' => $customer->id,
            'billing_name' => 'Taylor Jones',
            'billing_email' => 'taylor.jones@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 80.00,
            'subtotal_amount' => 72.73,
            'gst_amount' => 7.27,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'total_amount' => 80.00,
            'gst_amount' => 0.00,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 80.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payment.edit', $payment));

        $response->assertOk();
        $response->assertSee('INV-200001', false);
        $response->assertSee('Paid in full', false);
    }

    public function test_payment_store_can_email_a_receipt_when_requested(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Morgan',
            'surname' => 'Lee',
            'email' => 'morgan.lee@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-300001',
            'user_id' => $customer->id,
            'billing_name' => 'Morgan Lee',
            'billing_email' => 'morgan.lee@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 60.00,
            'subtotal_amount' => 54.55,
            'gst_amount' => 5.45,
        ]);

        Queue::fake();

        $response = $this->actingAs($admin)->post(route('admin.payment.store'), [
            'user_id' => $customer->id,
            'received_on' => now()->format('Y-m-d H:i:s'),
            'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
            'reference' => 'BT-300001',
            'total_amount' => 60.00,
            'notes' => 'Bank transfer',
            'email_receipt' => 1,
            'allocations_json' => json_encode([
                [
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => 60.00,
                ],
            ]),
        ]);

        $response->assertRedirect(route('admin.payment.index'));
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'morgan.lee@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->invoiceNumber === 'INV-300001'
                && $job->mailable->invoiceSummary === 'Invoice INV-300001';
        });
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
