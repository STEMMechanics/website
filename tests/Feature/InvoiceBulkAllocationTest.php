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

class InvoiceBulkAllocationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    private function invoice(array $attributes = []): Invoice
    {
        $invoice = Invoice::factory()->create($attributes + ['total_amount' => 330, 'issue_date' => '2026-09-01']);
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'workshop', 'quantity' => 30, 'details_json' => ['workshop' => ['hours' => 2, 'seats' => 15, 'venue_supplied' => true]]]);

        return $invoice;
    }

    private function preview(array $ids): array
    {
        return $this->get(route('admin.invoice.bulk-allocation.preview', ['invoice_ids' => $ids, 'version_id' => 1, 'review' => 1]))->assertOk()->viewData('preview');
    }

    public function test_preview_is_read_only_and_apply_is_idempotent(): void
    {
        $this->admin();
        $invoice = $this->invoice();
        $preview = $this->preview([$invoice->id]);
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->assertNull($preview['rows'][0]['warning']);
        $data = ['token' => $preview['token'], 'selected' => [0]];
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), $data)->assertOk();
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), $data)->assertOk();
        $this->assertDatabaseCount('finance_budgets', 1);
        $this->assertSame('330.00', $invoice->fresh()->total_amount);
    }

    public function test_ticket_invoices_are_not_allocated_by_invoice_bulk_actions(): void
    {
        $this->admin();
        $invoice = Invoice::factory()->create();
        Ticket::factory()->create(['invoice_id' => $invoice->id]);
        $preview = $this->preview([$invoice->id]);
        $this->assertCount(1, $preview['rows']);
        $this->assertSame('Allocation managed by workshop.', $preview['rows'][0]['warning']);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 0);
    }

    public function test_stale_blocked_tampered_and_other_users_previews_cannot_be_applied(): void
    {
        $this->admin();
        $invoice = $this->invoice();
        $preview = $this->preview([$invoice->id]);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [999]])->assertUnprocessable();
        $invoice->lines->first()->update(['details_json' => ['workshop' => ['hours' => 1, 'seats' => 30, 'venue_supplied' => true]]]);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertUnprocessable();
        $this->admin();
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 0);
        $this->actingAs(User::factory()->create())->get(route('admin.invoice.bulk-allocation.preview', ['invoice_ids' => [$invoice->id]]))->assertForbidden();
    }

    public function test_filters_and_select_all_use_the_same_customer_amount_date_type_and_allocation_query(): void
    {
        $this->admin();
        $wanted = $this->invoice(['billing_name' => 'Example School']);
        $other = $this->invoice(['billing_name' => 'Different Customer']);
        $filters = ['customer' => 'Example School', 'line_types' => ['workshop'], 'list_total_amount_min' => 300, 'list_total_amount_max' => 400, 'list_issue_date_min' => '2026-09-01', 'allocation_state' => 'not_allocated', 'allocation_selection' => 1];
        $this->getJson(route('admin.invoice.index', $filters))->assertOk()->assertExactJson(['names' => [(string) $wanted->id]]);
        $preview = $this->preview([$wanted->id]);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertOk();
        $this->getJson(route('admin.invoice.index', $filters))->assertExactJson(['names' => []]);
        $filters['allocation_state'] = 'automatic';
        $this->getJson(route('admin.invoice.index', $filters))->assertExactJson(['names' => [(string) $wanted->id]]);
        $this->get(route('admin.invoice.index'))->assertOk()->assertSee('Allocate 0 invoices');
    }

    public function test_payment_dates_and_plan_filters_match_actual_receipts_and_saved_allocations(): void
    {
        $this->admin();
        $invoice = $this->invoice();
        $payment = \App\Models\Payment::factory()->create(['kind' => 'payment', 'payment_method' => 'bank_transfer', 'gateway_status' => null, 'received_on' => '2026-09-03', 'total_amount' => 110]);
        $payment->allocations()->create(['invoice_id' => $invoice->id, 'allocated_amount' => 110]);
        $filters = ['allocation_selection' => 1, 'payment_from' => '2026-09-03', 'payment_to' => '2026-09-03'];
        $this->getJson(route('admin.invoice.index', $filters))->assertExactJson(['names' => [(string) $invoice->id]]);
        $this->getJson(route('admin.invoice.index', array_replace($filters, ['payment_from' => '2026-09-04'])))->assertExactJson(['names' => []]);
        $preview = $this->preview([$invoice->id]);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertOk();
        DB::table('finance_budgets')->update(['manual' => true]);
        $this->getJson(route('admin.invoice.index', ['allocation_selection' => 1, 'allocation_state' => 'manual', 'allocation_plan' => '1']))->assertExactJson(['names' => [(string) $invoice->id]]);
        $this->getJson(route('admin.invoice.index', ['allocation_selection' => 1, 'allocation_state' => 'not_allocated', 'allocation_plan' => '1']))->assertExactJson(['names' => []]);
    }

    public function test_missing_workshop_dimensions_require_review_instead_of_guessed_allocations(): void
    {
        $this->admin();
        $invoice = $this->invoice();
        $invoice->lines->first()->update(['details_json' => []]);
        $preview = $this->preview([$invoice->id]);
        $this->assertNotNull($preview['rows'][0]['warning']);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertUnprocessable();
        $this->assertDatabaseCount('finance_budgets', 0);
    }

    public function test_existing_manual_allocations_are_blocked_in_preview(): void
    {
        $this->admin();
        $invoice = $this->invoice();
        $preview = $this->preview([$invoice->id]);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $preview['token'], 'selected' => [0]])->assertOk();
        DB::table('finance_budgets')->update(['manual' => true]);
        $second = $this->preview([$invoice->id]);
        $this->assertNotNull($second['rows'][0]['warning']);
        $this->postJson(route('admin.invoice.bulk-allocation.apply'), ['token' => $second['token'], 'selected' => [0]])->assertUnprocessable();
        $this->assertDatabaseHas('finance_budgets', ['manual' => true]);
    }
}
