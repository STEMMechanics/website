<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class PublicInvoicePortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_invoice_portal_shows_invoice_number_and_amounts_without_user_details(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Pat',
            'surname' => 'Client',
            'email' => 'pat.client@example.com',
        ]);

        $invoice = Invoice::create([
            'invoice_number' => '9001',
            'user_id' => $user->id,
            'billing_name' => 'Pat Client Pty Ltd',
            'billing_email' => 'billing@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(14)->toDateString(),
            'subtotal_amount' => 100.00,
            'gst_amount' => 10.00,
            'total_amount' => 110.00,
        ]);

        $response = $this->get(route('invoice.public.pay.show', $invoice));

        $response->assertOk();
        $response->assertSee('Invoice #');
        $response->assertSee('9001');
        $response->assertSee('Outstanding');
        $response->assertDontSee('pat.client@example.com');
        $response->assertDontSee('Pat Client Pty Ltd');
    }

    public function test_signed_invoice_receipt_pdf_route_resolves_for_linked_payment(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110.00,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'total_amount' => 110.00,
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 110.00,
        ]);

        $url = URL::signedRoute('invoice.receipt.pdf', [
            'invoice' => $invoice,
            'payment' => $payment,
        ]);

        $this->get($url)->assertOk();
    }
}
