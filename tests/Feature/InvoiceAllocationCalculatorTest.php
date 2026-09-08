<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InvoiceAllocationCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_calculator_offers_active_plans_and_manual_figures_save_only_on_submission(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        $invoice = Invoice::factory()->create(['status' => 'issued', 'total_amount' => 110, 'gst_amount' => 10, 'subtotal_amount' => 100, 'created_by' => $admin->id]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'generic', 'line_total_ex_tax' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110]);
        $plan = (array) DB::table('finance_pricing_versions')->first();
        unset($plan['id']);
        DB::table('finance_pricing_versions')->insert(array_replace($plan, ['name' => 'Archived calculator plan', 'archived' => true]));
        DB::table('finance_pricing_versions')->insert(array_replace($plan, ['name' => 'Snapshot calculator plan', 'is_snapshot' => true]));
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()
            ->assertSee('aria-label="Calculate cost centre allocation"', false)
            ->assertSee('Billable travel hours / workshop')->assertSee('Apply to invoice')
            ->assertDontSee('Archived calculator plan')->assertDontSee('Snapshot calculator plan')
            ->assertDontSee('@js((string)');
        $this->get(route('admin.invoice.allocation.edit', $invoice))->assertOk()->assertSee('invoice-allocation-calculator-'.$invoice->id.'-editor');
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['inline' => 1, 'targets' => [1 => '12.34', 2 => '87.66']])->assertOk();
        $budget = DB::table('finance_budgets')->first();
        $this->assertTrue((bool) $budget->manual);
        $this->assertSame([1 => 1234, 2 => 8766], json_decode($budget->targets, true));
        $this->assertSame(100.0, (float) $line->fresh()->line_total_ex_tax);
        $this->assertSame(110.0, (float) $invoice->fresh()->total_amount);
    }

    public function test_ticket_only_invoice_keeps_allocation_at_the_workshop(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        $invoice = Invoice::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $ticket = Ticket::factory()->create(['invoice_id' => $invoice->id]);
        $line = InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'ticket', 'line_total_ex_tax' => 100, 'tax_amount' => 10, 'line_total_inc_tax' => 110, 'details_json' => ['workshop_id' => $ticket->workshop_id]]);
        $ticket->update(['invoice_line_id' => $line->id]);
        $this->get(route('admin.invoice.edit', $invoice))->assertOk()
            ->assertSee('Ticket allocation managed by workshop')
            ->assertDontSee('aria-label="Calculate cost centre allocation"', false);
    }
}
