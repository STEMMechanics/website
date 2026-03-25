<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
