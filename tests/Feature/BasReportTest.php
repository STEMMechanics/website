<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BasReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_bas_page_shows_generated_payment_summary_and_expense_description(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Casey',
            'surname' => 'Nguyen',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-100001',
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'description' => 'Robotics workshop ticket',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 2,
            'description' => 'Materials pack',
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'received_on' => '2026-02-12 09:30:00',
            'total_amount' => 110.00,
            'gst_amount' => 10.00,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_id' => $invoice->id,
            'allocated_amount' => 110.00,
        ]);

        Expense::factory()->create([
            'created_by' => $admin->id,
            'supplier' => 'STEM Supplies Co',
            'description' => 'Soldering kits for February classes',
            'invoice_id' => 'EXP-2201',
            'paid_on' => '2026-02-18',
            'total_amount' => 55.00,
            'gst_amount' => 5.00,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.bas.index', ['month' => '2026-02']));

        $response->assertOk();
        $response->assertSee('Robotics workshop ticket +1 more');
        $response->assertSee('Soldering kits for February classes');
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
