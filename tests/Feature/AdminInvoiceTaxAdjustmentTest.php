<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class AdminInvoiceTaxAdjustmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_partial_tax_adjustment_refund_with_decimal_quantity(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 1.00,
            'gst_amount' => 0.00,
            'total_amount' => 1.00,
        ]);
        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Test line',
            'quantity' => 1.00,
            'unit_price_ex_tax' => 1.00,
            'tax_rate' => 0.00,
            'line_total_ex_tax' => 1.00,
            'tax_amount' => 0.00,
            'line_total_inc_tax' => 1.00,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.tax_adjustment.create', $invoice))
            ->assertOk()
            ->assertSee('Partial quantities are allowed', false)
            ->assertSee('step="0.01"', false);

        $this->actingAs($admin)
            ->post(route('admin.tax_adjustment.store', $invoice), [
                'refund_qty' => [
                    $line->id => '0.55',
                ],
                'reason' => 'Partial refund requested.',
            ])
            ->assertRedirect();

        $adjustment = TaxAdjustment::query()->sole();

        $this->assertSame(-0.55, round((float) $adjustment->total_amount, 2));
        $this->assertSame(-0.55, round((float) $adjustment->subtotal_amount, 2));
        $this->assertSame(0.00, round((float) $adjustment->gst_amount, 2));
        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $line->id,
            'quantity' => 0.55,
            'line_total_inc_tax' => 0.55,
        ]);
    }

    public function test_admin_tax_adjustment_total_matches_the_preview_when_rounding_would_otherwise_drift(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 50.00,
            'gst_amount' => 0.00,
            'total_amount' => 50.00,
        ]);

        $lines = [];
        foreach ([1, 2, 3] as $lineNumber) {
            $lines[] = InvoiceLine::factory()->create([
                'invoice_id' => $invoice->id,
                'line_number' => $lineNumber,
                'description' => 'Rounding test line '.$lineNumber,
                'quantity' => 0.50,
                'unit_price_ex_tax' => 33.33,
                'tax_rate' => 0.00,
                'line_total_ex_tax' => 16.67,
                'tax_amount' => 0.00,
                'line_total_inc_tax' => 16.67,
            ]);
        }

        $this->actingAs($admin)
            ->post(route('admin.tax_adjustment.store', $invoice), [
                'refund_qty' => [
                    $lines[0]->id => '0.50',
                    $lines[1]->id => '0.50',
                    $lines[2]->id => '0.50',
                ],
                'reason' => 'Rounding check.',
            ])
            ->assertRedirect();

        $adjustment = TaxAdjustment::query()->sole();

        $this->assertSame(-50.00, round((float) $adjustment->total_amount, 2));
        $this->assertSame(-50.00, round((float) $adjustment->subtotal_amount, 2));
        $this->assertSame(0.00, round((float) $adjustment->gst_amount, 2));
    }

    public function test_tax_adjustment_that_clears_the_remaining_balance_marks_the_invoice_paid(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->subDays(7)->toDateString(),
            'due_date' => now()->subDay()->toDateString(),
            'subtotal_amount' => 35.00,
            'gst_amount' => 0.00,
            'total_amount' => 35.00,
        ]);
        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Workshop booking',
            'quantity' => 1.00,
            'unit_price_ex_tax' => 35.00,
            'tax_rate' => 0.00,
            'line_total_ex_tax' => 35.00,
            'tax_amount' => 0.00,
            'line_total_inc_tax' => 35.00,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CASH,
            'total_amount' => 17.50,
            'gst_amount' => 0.00,
        ]);
        InvoicePaymentAllocation::query()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 17.50,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.tax_adjustment.store', $invoice), [
                'refund_qty' => [
                    $line->id => '0.50',
                ],
                'reason' => 'Balance correction.',
            ])
            ->assertRedirect();

        $freshInvoice = $invoice->fresh();

        $this->assertSame(Invoice::STATUS_PAID, (string) $freshInvoice?->status);
        $this->assertSame(0.00, round((float) $freshInvoice?->outstandingAmount(), 2));
    }

    public function test_admin_tax_adjustment_email_includes_the_original_invoice_pdf(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'status' => Invoice::STATUS_ISSUED,
            'billing_email' => 'customer@example.com',
            'subtotal_amount' => 20.00,
            'gst_amount' => 2.00,
            'total_amount' => 22.00,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Workshop booking',
            'quantity' => 1.00,
            'unit_price_ex_tax' => 20.00,
            'tax_rate' => 0.10,
            'line_total_ex_tax' => 20.00,
            'tax_amount' => 2.00,
            'line_total_inc_tax' => 22.00,
        ]);
        $adjustment = TaxAdjustment::query()->create([
            'invoice_id' => $invoice->id,
            'adjustment_number' => 'TA-EMAIL-1',
            'issue_date' => now()->toDateString(),
            'subtotal_amount' => -20.00,
            'gst_amount' => -2.00,
            'total_amount' => -22.00,
            'notes' => 'Waived at attendance',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]))
            ->post(route('admin.tax_adjustment.email', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]));

        $response->assertRedirect(route('admin.tax_adjustment.edit', ['invoice' => $invoice, 'taxAdjustment' => $adjustment]));
        Queue::assertPushed(SendEmail::class, 1);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            if (! $job->mailable instanceof FinanceDocumentPdf) {
                return false;
            }

            $reflection = new ReflectionClass($job->mailable);
            $property = $reflection->getProperty('extraAttachmentsPayload');
            $property->setAccessible(true);
            $attachments = $property->getValue($job->mailable);

            $this->assertCount(1, $attachments);
            $this->assertStringStartsWith('invoice-', $attachments[0]['filename']);

            return true;
        });
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
