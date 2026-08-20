<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\TaxAdjustment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfPaymentDetailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_invoice_uses_outstanding_balance_and_lists_each_payment(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => '8683',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 19.91,
            'gst_amount' => 1.99,
            'total_amount' => 21.90,
        ]);
        $line = $invoice->lines()->create([
            'line_number' => 1,
            'kind' => 'product',
            'description' => 'Cardboard Pinball Machine Kit',
            'quantity' => 2,
            'unit_price_ex_tax' => 9.95,
            'tax_rate' => 0.10,
            'line_total_ex_tax' => 19.91,
            'tax_amount' => 1.99,
            'line_total_inc_tax' => 21.90,
        ]);
        $firstPayment = Payment::factory()->create([
            'kind' => Payment::KIND_PAYMENT,
            'received_on' => now()->subDay(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Store order 1000 - deposit',
            'total_amount' => 10.00,
        ]);
        $secondPayment = Payment::factory()->create([
            'kind' => Payment::KIND_PAYMENT,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Store order 1000 - balance',
            'total_amount' => 11.90,
        ]);
        $secondPayment->square_card_brand = 'VISA';
        $secondPayment->square_card_last4 = '3255';
        $secondPayment->square_payment_id = 'square-payment-transaction-123';
        $secondPayment->save();
        $invoice->allocations()->create(['payment_id' => $firstPayment->id, 'allocated_amount' => 10.00]);
        $invoice->allocations()->create(['payment_id' => $secondPayment->id, 'allocated_amount' => 11.90]);

        $html = view('pdf.invoice', [
            'invoice' => $invoice->fresh(['user', 'lines']),
            'itemPages' => [[[
                'kind' => $line->kind,
                'description' => $line->description,
                'quantity' => (float) $line->quantity,
                'unit_price_ex_tax' => (float) $line->unit_price_ex_tax,
                'tax_rate' => (float) $line->tax_rate,
                'line_total_ex_tax' => (float) $line->line_total_ex_tax,
            ]]],
            'adjustments' => collect(),
        ])->render();

        $this->assertStringContainsString('AMOUNT DUE', $html);
        $this->assertStringNotContainsString('PAID IN FULL', $html);
        $this->assertStringContainsString('Payments / Credits', $html);
        $this->assertStringNotContainsString('payment history', $html);
        $this->assertStringContainsString('VISA ending 3255', $html);
        $this->assertStringContainsString('square-payment-transaction-123', $html);
        $this->assertStringContainsString('<th style="width:12%;">METHOD</th>', $html);
        $this->assertStringContainsString('<th style="width:26%;">CARD</th>', $html);
        $this->assertStringNotContainsString('<th style="width:13%;">RECEIPT</th>', $html);
        $this->assertStringNotContainsString('>STATUS</th>', $html);
        $this->assertSame(0.0, $invoice->fresh()->displayOutstandingAmount());
    }

    public function test_tax_credit_is_included_in_totals_and_invoice_activity(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => '8690',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 20.00,
            'gst_amount' => 2.00,
            'total_amount' => 22.00,
        ]);
        TaxAdjustment::factory()->for($invoice)->create([
            'adjustment_number' => 'TAX-CREDIT-1001',
            'issue_date' => now(),
            'subtotal_amount' => -20.00,
            'gst_amount' => -2.00,
            'total_amount' => -22.00,
        ]);

        $html = view('pdf.invoice', [
            'invoice' => $invoice->fresh(['user']),
            'itemPages' => [[]],
            'adjustments' => collect(),
        ])->render();

        $this->assertStringContainsString('Payments / Credits', $html);
        $this->assertStringContainsString('- $ 22.00', $html);
        $this->assertStringContainsString('Credit', $html);
        $this->assertStringNotContainsString('Tax Credit', $html);
        $this->assertStringContainsString('TAX-CREDIT-1001', $html);
        $this->assertSame(0.0, $invoice->fresh()->displayOutstandingAmount());
    }

    public function test_payments_and_credits_are_combined_in_the_invoice_total(): void
    {
        $invoice = Invoice::factory()->create([
            'total_amount' => 100.00,
            'subtotal_amount' => 90.91,
            'gst_amount' => 9.09,
        ]);
        TaxAdjustment::factory()->for($invoice)->create(['total_amount' => -40.00]);
        $payment = Payment::factory()->create([
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 60.00,
        ]);
        $invoice->allocations()->create(['payment_id' => $payment->id, 'allocated_amount' => 60.00]);

        $html = view('pdf.invoice', [
            'invoice' => $invoice->fresh(['user']),
            'itemPages' => [[]],
            'adjustments' => collect(),
        ])->render();

        $this->assertStringContainsString('Payments / Credits', $html);
        $this->assertStringContainsString('- $ 100.00', $html);
        $this->assertStringNotContainsString('- $ 60.00', $html);
        $this->assertStringNotContainsString('- $ 40.00', $html);
        $this->assertStringContainsString('$ 60.00', $html);
        $this->assertStringContainsString('$ 40.00', $html);
        $this->assertSame(0.0, $invoice->fresh()->displayOutstandingAmount());
    }

    public function test_invoice_without_payments_or_credits_shows_positive_zero_and_no_activity_page(): void
    {
        $invoice = Invoice::factory()->create([
            'total_amount' => 22.00,
            'subtotal_amount' => 20.00,
            'gst_amount' => 2.00,
        ]);

        $html = view('pdf.invoice', [
            'invoice' => $invoice->fresh(['user']),
            'itemPages' => [[]],
            'adjustments' => collect(),
        ])->render();

        $this->assertStringContainsString('Payments / Credits', $html);
        $this->assertStringContainsString('<td class="value">$ 0.00</td>', $html);
        $this->assertStringNotContainsString('- $ 0.00', $html);
        $this->assertStringNotContainsString('TRANSACTION ID', $html);
    }

    public function test_payment_receipt_can_render_purchased_items(): void
    {
        $html = view('pdf.payment-receipt', [
            'receiptNumber' => '1140',
            'amountPaid' => 21.90,
            'paidOn' => now()->format('M j, Y g:i a'),
            'paymentMethod' => 'Credit Card',
            'invoiceNumber' => '8683',
            'reference' => '',
            'gatewayProvider' => '',
            'gatewayStatus' => '',
            'transactionId' => '',
            'squareOrderId' => '',
            'cardBrand' => '',
            'cardLast4' => '',
            'purchasedItems' => [[
                'description' => 'Cardboard Pinball Machine Kit',
                'quantity' => 2,
                'line_total_inc_tax' => 21.90,
            ]],
        ])->render();

        $this->assertStringContainsString('PURCHASED ITEMS', $html);
        $this->assertStringContainsString('Cardboard Pinball Machine Kit', $html);
        $this->assertStringContainsString('TOTAL (INC GST)', $html);
    }
}
