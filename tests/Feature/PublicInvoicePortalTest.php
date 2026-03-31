<?php

namespace Tests\Feature;

use App\Http\Controllers\InvoiceController;
use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Mail\StoreOrderPaid;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\Token;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\URL;
use Mockery;
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

        /** @var Invoice $invoice */
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
        $response->assertSeeText('Invoice '.$invoice->invoice_number);
        $response->assertSeeText('Issued Date');
        $response->assertSeeText('Outstanding');
        $response->assertDontSee('pat.client@example.com');
        $response->assertDontSee('Pat Client Pty Ltd');
    }

    public function test_signed_invoice_receipt_pdf_route_resolves_for_linked_payment(): void
    {
        $user = User::factory()->create();
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110.00,
        ]);
        /** @var Payment $payment */
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
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $user->id,
            'quote_number' => 'Q-1000',
            'status' => Quote::STATUS_ACCEPTED,
            'quote_date' => now()->toDateString(),
        ]);
        /** @var Invoice $invoice */
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
            ->assertSeeText('Invoice '.$invoice->invoice_number)
            ->assertDontSeeText('Reference');
    }

    public function test_account_invoice_receipt_show_displays_credit_split_details(): void
    {
        $user = User::factory()->create([
            'firstname' => 'Alex',
            'surname' => 'Customer',
            'email' => 'alex.customer@example.com',
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Alex Customer',
            'billing_email' => 'alex.customer@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 100.00,
        ]);

        /** @var Payment $creditPayment */
        $creditPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 52.50,
            'reference' => 'Account Credit',
        ]);
        /** @var Payment $cardPayment */
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

    public function test_invoice_magic_portal_shows_order_number_when_invoice_is_linked_to_store_order(): void
    {
        $user = User::factory()->create();
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 55.00,
        ]);
        /** @var StoreOrder $order */
        $order = StoreOrder::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_number' => '1003',
        ]);
        /** @var Token $token */
        $token = Token::query()->create([
            'user_id' => $user->id,
            'type' => 'invoice-access',
            'data' => [
                'invoice_id' => $invoice->id,
            ],
            'expires_at' => now()->addDays(30),
        ]);

        $this->get(route('invoice.magic', ['token' => $token->id]))
            ->assertOk()
            ->assertSeeText('Order')
            ->assertSeeText('1003');

        $this->assertSame((int) $invoice->id, (int) $order->invoice_id);
    }

    public function test_invoice_payment_receipt_email_sends_separate_credit_and_card_receipts_when_credit_is_used(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'firstname' => 'Alex',
            'surname' => 'Customer',
            'email' => 'alex.customer@example.com',
        ]);

        /** @var Invoice $invoice */
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

        /** @var Payment $creditPayment */
        $creditPayment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 100.00,
            'reference' => 'Account Credit',
        ]);
        /** @var Payment $cardPayment */
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
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($invoice): bool {
            /** @var PaymentReceiptPdf $mailable */
            $mailable = $job->mailable;
            $rendered = $mailable->build()->render();

            return $mailable instanceof PaymentReceiptPdf
                && $mailable->receiptNumber === (string) $mailable->receiptNumber
                && $mailable->paymentMethod === 'Credit Card'
                && $mailable->creditAppliedAmount === null
                && $mailable->creditReferenceSummary === null
                && $mailable->orderTotalAmount === null
                && $mailable->hasInvoiceAttachment === true
                && $mailable->build()->subject === 'Your payment receipt for invoice '.$invoice->invoice_number
                && str_contains($rendered, 'Your invoice and payment receipt are attached.');
        });
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($cardPayment, $invoice): bool {
            /** @var PaymentReceiptPdf $mailable */
            $mailable = $job->mailable;
            $rendered = $mailable->build()->render();

            return $mailable instanceof PaymentReceiptPdf
                && $mailable->receiptNumber === (string) ($cardPayment->id + 1)
                && $mailable->paymentMethod === 'Account Credit'
                && $mailable->creditAppliedAmount === null
                && $mailable->orderTotalAmount === null
                && $mailable->hasInvoiceAttachment === false
                && $mailable->build()->subject === 'Your payment receipt for invoice '.$invoice->invoice_number
                && str_contains($rendered, 'Your credit receipt is attached.');
        });
    }

    public function test_invoice_payment_email_uses_order_paid_mail_when_invoice_is_linked_to_store_order(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'firstname' => 'Robin',
            'surname' => 'Customer',
            'email' => 'robin@example.com',
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'status' => Invoice::STATUS_PAID,
            'invoice_number' => '8671',
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
            'total_amount' => 100.00,
        ]);

        /** @var StoreOrder $order */
        $order = StoreOrder::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_number' => '1004',
            'billing_email' => 'robin@example.com',
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);

        /** @var Payment $payment */
        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 100.00,
            'reference' => 'Square Payment',
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 100.00,
        ]);

        $controller = app(InvoiceController::class);
        $method = new ReflectionMethod($controller, 'sendPaymentReceiptEmail');
        $method->setAccessible(true);
        $method->invoke($controller, $invoice, $payment);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'robin@example.com'
                && $job->mailable instanceof StoreOrderPaid;
        });
        Queue::assertNotPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->mailable instanceof PaymentReceiptPdf;
        });

        $this->assertSame((int) $invoice->id, (int) $order->invoice_id);
    }

    public function test_public_invoice_payment_shows_receipt_email_message(): void
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

            return (int) data_get($payload, 'amount_money.amount') === 11000
                && str_contains($idempotencyKey, '-amt-11000')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 11000],
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

        $user = User::factory()->create([
            'firstname' => 'Pat',
            'surname' => 'Customer',
            'email' => 'pat.customer@example.com',
        ]);

        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Pat Customer',
            'billing_email' => 'pat.customer@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'subtotal_amount' => 100.00,
            'gst_amount' => 10.00,
            'total_amount' => 110.00,
        ]);

        $response = $this->post(route('invoice.public.pay.process', $invoice), [
            'source_id' => 'cnon:card-nonce-ok',
        ]);

        $response->assertRedirect(route('invoice.public.pay.show', $invoice));
        $response->assertSessionHas('message', 'Payment completed successfully. Your receipt has been emailed.');
        $response->assertSessionHas('message-title', 'Payment success');
    }
}
