<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Console\Commands\SendPendingBankTransferPaymentRemindersCommand;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use App\Mail\PaymentReceiptPdf;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
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
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
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
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
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

    public function test_payment_index_links_allocated_invoice_numbers_to_invoice_edit_pages(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Link',
            'surname' => 'Tester',
            'email' => 'link.tester@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-500001',
            'user_id' => $customer->id,
            'billing_name' => 'Link Tester',
            'billing_email' => 'link.tester@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
            'total_amount' => 100.00,
            'gst_amount' => 0.00,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 100.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payment.index'));

        $response->assertOk();
        $response->assertSee(route('admin.invoice.edit', $invoice), false);
        $response->assertSee('Invoice #INV-500001', false);
    }

    public function test_payment_edit_page_shows_bank_transfer_clearance_and_receipt_controls(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Jordan',
            'surname' => 'Miles',
            'email' => 'jordan.miles@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-200010',
            'user_id' => $customer->id,
            'billing_name' => 'Jordan Miles',
            'billing_email' => 'jordan.miles@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 75.00,
            'subtotal_amount' => 68.18,
            'gst_amount' => 6.82,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
            'total_amount' => 75.00,
            'gst_amount' => 0.00,
            'cleared_at' => null,
            'notes' => 'Workshop "Newtons Cradle" ticket purchase',
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 75.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.payment.edit', $payment));

        $response->assertOk();
        $response->assertSee('Email receipt to customer', false);
        $response->assertSee('Mark this bank transfer as cleared', false);
        $response->assertSee('Pending clearance', false);
        $response->assertSee('x-bind:disabled="selectedPaymentMethod === bankTransferMethod && ! bankTransferCleared"', false);
        $response->assertSeeText('Workshop "Newtons Cradle" ticket purchase');
    }

    public function test_pending_bank_transfer_remains_unpaid_until_it_is_cleared_and_can_email_receipt_on_update(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Casey',
            'surname' => 'Brown',
            'email' => 'casey.brown@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-300020',
            'user_id' => $customer->id,
            'billing_name' => 'Casey Brown',
            'billing_email' => 'casey.brown@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 120.00,
            'subtotal_amount' => 109.09,
            'gst_amount' => 10.91,
        ]);
        $ticket = Ticket::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => Ticket::STATUS_PENDING_XFER,
        ]);

        Queue::fake();

        $storeResponse = $this->actingAs($admin)->post(route('admin.payment.store'), [
            'user_id' => $customer->id,
            'received_on' => now()->format('Y-m-d H:i:s'),
            'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
            'reference' => 'BT-300020',
            'total_amount' => 120.00,
            'notes' => 'Waiting for clearance',
            'email_receipt' => 1,
            'allocations_json' => json_encode([
                [
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => 120.00,
                ],
            ]),
        ]);

        $storeResponse->assertRedirect(route('admin.payment.index'));
        $storeResponse->assertSessionHasNoErrors();

        $payment = Payment::query()->where('reference', 'BT-300020')->firstOrFail();
        $invoice->refresh();

        $this->assertNull($payment->cleared_at);
        $this->assertTrue($payment->isPendingBankTransfer());
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertSame(Ticket::STATUS_PENDING_XFER, (int) $ticket->fresh()->status);
        Queue::assertNotPushed(SendEmail::class);

        $updateResponse = $this->actingAs($admin)->put(route('admin.payment.update', $payment), [
            'user_id' => $customer->id,
            'reference' => 'BT-300020',
            'notes' => 'Cleared by bank',
            'email_receipt' => 1,
            'bank_transfer_cleared' => 1,
            'allocations_json' => json_encode([
                [
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => 120.00,
                ],
            ]),
        ]);

        $updateResponse->assertRedirect();
        $updateResponse->assertSessionHasNoErrors();

        $payment->refresh();
        $invoice->refresh();

        $this->assertNotNull($payment->cleared_at);
        $this->assertFalse($payment->isPendingBankTransfer());
        $this->assertSame(Invoice::STATUS_PAID, $invoice->status);
        $this->assertSame(Ticket::STATUS_PAID, (int) $ticket->fresh()->status);

        $secondUpdateResponse = $this->actingAs($admin)->put(route('admin.payment.update', $payment), [
            'user_id' => $customer->id,
            'reference' => 'BT-300020',
            'notes' => 'Attempting to un-clear',
            'email_receipt' => 0,
            'allocations_json' => json_encode([
                [
                    'invoice_id' => $invoice->id,
                    'allocated_amount' => 120.00,
                ],
            ]),
        ]);

        $secondUpdateResponse->assertRedirect();
        $secondUpdateResponse->assertSessionHasNoErrors();

        $payment->refresh();
        $this->assertNotNull($payment->cleared_at);
        $this->assertFalse($payment->isPendingBankTransfer());

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($customer): bool {
            return $job->to === 'casey.brown@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->recipientName === $customer->getName()
                && $job->mailable->invoiceNumber === 'INV-300020';
        });
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
            'bank_transfer_cleared' => 1,
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
                && $job->mailable->invoiceSummary === null;
        });
    }

    public function test_payment_store_can_email_a_receipt_for_multiple_allocations(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Avery',
            'surname' => 'Ng',
            'email' => 'avery.ng@example.com',
        ]);

        $firstInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-300010',
            'user_id' => $customer->id,
            'billing_name' => 'Avery Ng',
            'billing_email' => 'avery.ng@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 40.00,
            'subtotal_amount' => 36.36,
            'gst_amount' => 3.64,
        ]);
        $secondInvoice = Invoice::factory()->create([
            'invoice_number' => 'INV-300011',
            'user_id' => $customer->id,
            'billing_name' => 'Avery Ng',
            'billing_email' => 'avery.ng@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 20.00,
            'subtotal_amount' => 18.18,
            'gst_amount' => 1.82,
        ]);

        Queue::fake();

        $response = $this->actingAs($admin)->post(route('admin.payment.store'), [
            'user_id' => $customer->id,
            'received_on' => now()->format('Y-m-d H:i:s'),
            'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
            'reference' => 'BT-300010',
            'total_amount' => 60.00,
            'notes' => 'Bank transfer',
            'bank_transfer_cleared' => 1,
            'email_receipt' => 1,
            'allocations_json' => json_encode([
                [
                    'invoice_id' => $firstInvoice->id,
                    'allocated_amount' => 40.00,
                ],
                [
                    'invoice_id' => $secondInvoice->id,
                    'allocated_amount' => 20.00,
                ],
            ]),
        ]);

        $response->assertRedirect(route('admin.payment.index'));
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'avery.ng@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->invoiceNumber === 'INV-300010, INV-300011'
                && $job->mailable->invoiceSummary === "INV-300010 (\$40.00 paid)\nINV-300011 (\$20.00 paid)";
        });
    }

    public function test_payment_receipt_pdf_view_renders_an_allocation_breakdown_for_multiple_invoices(): void
    {
        $html = view('pdf.payment-receipt', [
            'receiptTitle' => 'Payment Receipt',
            'isRefund' => false,
            'amountLabel' => 'Amount Paid',
            'receiptNumber' => '123',
            'invoiceLabel' => 'Invoice Numbers',
            'invoiceNumber' => 'INV-1, INV-2',
            'invoiceSummary' => "INV-1 (\$10.00 paid)\nINV-2 (\$20.00 paid)",
            'customerName' => 'Test Customer',
            'amountPaid' => 30.00,
            'gstAmount' => 0.00,
            'paymentMethod' => 'Bank Transfer',
            'paidOn' => 'Mar 30, 2026 12:51 pm',
            'reference' => '',
            'gatewayProvider' => '',
            'gatewayStatus' => '',
            'transactionId' => '',
            'squareOrderId' => '',
            'cardBrand' => '',
            'cardLast4' => '',
            'squareReceiptUrl' => '',
            'gatewayProcessedAt' => '',
            'footerMessage' => 'Thanks',
        ])->render();

        $this->assertStringContainsString('Invoice Numbers', $html);
        $this->assertStringNotContainsString('INV-1, INV-2', $html);
        $this->assertStringContainsString('INV-1 ($10.00 paid)', $html);
        $this->assertStringContainsString('INV-2 ($20.00 paid)', $html);
    }

    public function test_payment_receipt_email_view_renders_multi_line_summary_as_bullets(): void
    {
        $html = app(\Illuminate\Mail\Markdown::class)->render('emails.payment-receipt', [
            'recipientName' => 'Avery Ng',
            'invoiceNumber' => 'INV-300010, INV-300011',
            'receiptNumber' => '123',
            'amount' => '$60.00',
            'paidOn' => 'Mar 30, 2026 12:51 pm',
            'paymentMethod' => 'Bank Transfer',
            'receiptUrl' => null,
            'isRefund' => false,
            'invoiceSummary' => "INV-300010 (\$40.00 paid)\nINV-300011 (\$20.00 paid)",
            'statusSummary' => '',
            'outstandingBeforeSummary' => null,
            'appliedAmountSummary' => null,
            'creditSummary' => null,
            'creditAppliedAmount' => null,
            'creditReferenceSummary' => null,
            'orderTotalAmount' => null,
            'hasInvoiceAttachment' => false,
        ]);

        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('<li', $html);
        $this->assertStringContainsString('INV-300010 ($40.00 paid)', $html);
        $this->assertStringContainsString('INV-300011 ($20.00 paid)', $html);
    }

    public function test_admin_pending_bank_transfer_digest_command_queues_a_daily_reminder_email(): void
    {
        Mail::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Blake',
            'surname' => 'Reed',
            'email' => 'blake.reed@example.com',
        ]);

        $firstPayment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
            'received_on' => now()->subDays(3),
            'total_amount' => 50.00,
            'gst_amount' => 0.00,
            'cleared_at' => null,
            'reference' => 'BT-REM-1',
        ]);
        $secondPayment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'payment_method' => Payment::PAYMENT_METHOD_BANK_TRANSFER,
            'received_on' => now()->subDays(4),
            'total_amount' => 75.00,
            'gst_amount' => 0.00,
            'cleared_at' => null,
            'reference' => 'BT-REM-2',
        ]);

        $this->assertCount(2, Payment::query()
            ->pendingBankTransfers()
            ->where('received_on', '<', now()->subDays(2))
            ->get());
        $this->assertSame([
            strtolower((string) $admin->email),
        ], User::query()
            ->whereHas('groups', fn ($query) => $query->where('slug', 'admin'))
            ->pluck('email')
            ->map(fn ($email) => strtolower((string) $email))
            ->filter()
            ->values()
            ->all());

        $buffer = new BufferedOutput();
        $command = $this->app->make(SendPendingBankTransferPaymentRemindersCommand::class);
        $command->setOutput(new OutputStyle(new ArrayInput([]), $buffer));
        $command->handle();
        $this->assertStringContainsString('Queued pending bank transfer reminders', $buffer->fetch());

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
