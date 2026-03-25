<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoiceController;
use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use ReflectionMethod;
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
        $response->assertSeeText('Invoice Payment');
        $response->assertSeeText('Issued Date');
        $response->assertSeeText('Outstanding');
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

    public function test_account_invoice_page_shows_linked_quote_button(): void
    {
        $user = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $user->id,
            'quote_number' => 'Q-1000',
            'status' => Quote::STATUS_ACCEPTED,
            'quote_date' => now()->toDateString(),
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'quote_id' => $quote->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110.00,
        ]);

        $this->actingAs($user)
            ->get(route('account.invoice.show', $invoice))
            ->assertOk()
            ->assertSeeText('Q-1000')
            ->assertSeeText('Invoice #'.$invoice->invoice_number)
            ->assertDontSeeText('Reference');
    }

    public function test_account_invoice_receipt_show_displays_credit_split_details(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Alex',
            'surname' => 'Customer',
            'email' => 'alex.customer@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Alex Customer',
            'billing_email' => 'alex.customer@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 100.00,
        ]);

        $creditPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 52.50,
            'reference' => 'Account Credit',
        ]);
        $cardPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 47.50,
            'reference' => 'Square Payment',
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $creditPayment->id,
            'allocated_amount' => 52.50,
        ]);
        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $cardPayment->id,
            'allocated_amount' => 47.50,
        ]);

        $this->actingAs($user)
            ->get(route('account.invoice.receipt.show', [
                'invoice' => $invoice,
                'payment' => $cardPayment,
            ]))
            ->assertOk()
            ->assertSeeText('Account Credit + Credit Card')
            ->assertSeeText('Account Credit Applied')
            ->assertSeeText('$52.50')
            ->assertSeeText('Credit Reference')
            ->assertSeeText('Invoice Total')
            ->assertSeeText('$100.00');
    }

    public function test_invoice_payment_receipt_email_sends_separate_credit_and_card_receipts_when_credit_is_used(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'firstname' => 'Alex',
            'surname' => 'Customer',
            'email' => 'alex.customer@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Alex Customer',
            'billing_email' => 'alex.customer@example.com',
            'status' => Invoice::STATUS_PAID,
            'invoice_number' => '8666',
            'subtotal_amount' => 181.82,
            'gst_amount' => 18.18,
            'total_amount' => 200.00,
        ]);

        $creditPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 100.00,
            'reference' => 'Account Credit',
        ]);
        $cardPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 100.00,
            'reference' => 'Square Payment',
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $creditPayment->id,
            'allocated_amount' => 100.00,
        ]);
        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $cardPayment->id,
            'allocated_amount' => 100.00,
        ]);

        $controller = app(InvoiceController::class);
        $method = new ReflectionMethod($controller, 'sendPaymentReceiptEmail');
        $method->setAccessible(true);
        $method->invoke($controller, $invoice, $cardPayment);

        Queue::assertPushed(SendEmail::class, 2);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->receiptNumber === (string) $job->mailable->receiptNumber
                && $job->mailable->paymentMethod === 'Credit Card'
                && $job->mailable->creditAppliedAmount === null
                && $job->mailable->creditReferenceSummary === null
                && $job->mailable->orderTotalAmount === null;
        });
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($cardPayment): bool {
            return $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->receiptNumber === (string) ($cardPayment->id + 1)
                && $job->mailable->paymentMethod === 'Account Credit'
                && $job->mailable->creditAppliedAmount === null
                && $job->mailable->orderTotalAmount === null;
        });
    }
}
