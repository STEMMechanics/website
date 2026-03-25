<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class AdminUserCreditTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_user_edit_page_shows_account_credit(): void
    {
        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'firstname' => 'Credit',
            'surname' => 'Holder',
            'email' => 'credit-holder@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Credit Holder',
            'billing_email' => 'credit-holder@example.com',
            'total_amount' => 20.00,
            'subtotal_amount' => 18.18,
            'gst_amount' => 1.82,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 20.00,
            'gst_amount' => 0,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-123',
            'square_paid_money_amount' => 2000,
            'square_refunded_money_amount' => 0,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 5.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.user.edit', $user));

        $response->assertOk();
        $response->assertSee('Account Credit');
        $response->assertSee('$15.00');
        $response->assertSee('Card-refundable');
        $response->assertSee('Payments');
    }

    public function test_admin_user_finance_page_shows_payment_ledger_and_refund_button(): void
    {
        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'firstname' => 'Credit',
            'surname' => 'Holder',
            'email' => 'credit-holder@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Credit Holder',
            'billing_email' => 'credit-holder@example.com',
            'total_amount' => 20.00,
            'subtotal_amount' => 18.18,
            'gst_amount' => 1.82,
        ]);
        $order = StoreOrder::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'order_number' => '1004',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Store order 1004',
            'total_amount' => 20.00,
            'gst_amount' => 0,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-456',
            'square_paid_money_amount' => 2000,
            'square_refunded_money_amount' => 0,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 5.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.user.payments', $user));

        $response->assertOk();
        $response->assertSee('Payments');
        $response->assertSee('Account Credit');
        $response->assertSee('Card-refundable');
        $response->assertSee('Refund $15.00');
        $response->assertSee((string) $payment->id);
        $response->assertSee(route('admin.invoice.edit', $invoice), false);
        $response->assertSee('#'.$order->order_number);
        $response->assertDontSee('Store order 1004');
    }

    public function test_admin_user_finance_page_explains_payment_reallocation_across_cancelled_tickets(): void
    {
        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'firstname' => 'Credit',
            'surname' => 'Holder',
            'email' => 'credit-holder@example.com',
        ]);
        $originalInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Credit Holder',
            'billing_email' => 'credit-holder@example.com',
            'total_amount' => 49.99,
            'subtotal_amount' => 45.45,
            'gst_amount' => 4.54,
        ]);
        $replacementInvoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Credit Holder',
            'billing_email' => 'credit-holder@example.com',
            'total_amount' => 49.99,
            'subtotal_amount' => 45.45,
            'gst_amount' => 4.54,
        ]);

        Ticket::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $originalInvoice->id,
            'reference_code' => 'RUANZ5',
            'status' => Ticket::STATUS_CANCELLED,
        ]);
        Ticket::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $replacementInvoice->id,
            'reference_code' => 'WRR97H',
            'status' => Ticket::STATUS_PAID,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Workshop Newtons Cradle ticket [RUANZ5]',
            'total_amount' => 49.99,
            'gst_amount' => 0,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-reallocated',
            'square_paid_money_amount' => 4999,
            'square_refunded_money_amount' => 0,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $originalInvoice->id,
            'allocated_amount' => 49.99,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $replacementInvoice->id,
            'allocated_amount' => 49.99,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.user.payments', $user));

        $response->assertOk();
        $response->assertSee('Cancelled');
        $response->assertSee('Linked - $49.99');
        $response->assertSee('#'.$originalInvoice->invoice_number);
        $response->assertSee('#'.$replacementInvoice->invoice_number);
        $response->assertSee('RUANZ5 (Cancelled)');
        $response->assertSee('WRR97H (Paid)');
    }

    public function test_admin_square_refund_generates_idempotency_key_when_omitted(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'firstname' => 'Credit',
            'surname' => 'Holder',
            'email' => 'credit-holder@example.com',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $user->id,
            'created_by' => $admin->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Square payment',
            'total_amount' => 20.00,
            'gst_amount' => 0,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-refund-1',
            'square_paid_money_amount' => 2000,
            'square_refunded_money_amount' => 0,
        ]);

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createRefund')
            ->once()
            ->with(Mockery::on(function (array $payload): bool {
                return isset($payload['idempotency_key'])
                    && trim((string) $payload['idempotency_key']) !== ''
                    && ($payload['payment_id'] ?? null) === 'sq-test-refund-1'
                    && (int) ($payload['amount_money']['amount'] ?? 0) === 1500;
            }))
            ->andReturn([
                'refund' => [
                    'id' => 'sq-refund-1',
                    'status' => 'COMPLETED',
                    'amount_money' => ['amount' => 1500],
                    'created_at' => now()->toIso8601String(),
                    'updated_at' => now()->toIso8601String(),
                ],
            ]);
        $this->app->instance(SquareApiService::class, $squareApi);

        $response = $this->actingAs($admin)->post(route('admin.payment.square.refund', $payment), [
            'amount' => '15.00',
            'reason' => 'Partial refund',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('payments', [
            'refund_of_payment_id' => $payment->id,
            'kind' => Payment::KIND_REFUND,
            'total_amount' => 15.00,
        ]);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($user): bool {
            return $job->to === $user->email
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->isRefund === true;
        });
    }

    public function test_admin_user_index_merges_user_data_and_indents_child_accounts(): void
    {
        $admin = $this->createAdminUser();
        $parent = User::factory()->create([
            'firstname' => 'Parent',
            'surname' => 'Account',
            'email' => 'parent@example.com',
        ]);
        $child = User::factory()->create([
            'firstname' => 'Child',
            'surname' => 'Account',
            'email' => 'child@example.com',
            'parent_user_id' => $parent->id,
        ]);

        UserGroup::query()->create([
            'user_id' => $parent->id,
            'slug' => 'mentor',
        ]);
        Media::factory()->create([
            'user_id' => $parent->id,
            'size' => 2048,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $parent->id,
            'billing_name' => 'Parent Account',
            'billing_email' => 'parent@example.com',
            'total_amount' => 20.00,
            'subtotal_amount' => 18.18,
            'gst_amount' => 1.82,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $parent->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 20.00,
            'gst_amount' => 0,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-789',
            'square_paid_money_amount' => 2000,
            'square_refunded_money_amount' => 0,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 5.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.user.index'));

        $response->assertOk();
        $response->assertSee('User data');
        $response->assertSee($parent->email);
        $response->assertSee('$15.00');
        $response->assertSee(route('admin.user.payments', $parent), false);
        $response->assertSee('Child account');
        $response->assertSee('Parent:');
        $response->assertSee('mentor');
        $response->assertSee('1 file');
        $response->assertSee('2 KB');
        $response->assertSee($child->email);
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
