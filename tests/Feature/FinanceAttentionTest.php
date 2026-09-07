<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinanceAttention;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class FinanceAttentionTest extends TestCase
{
    use RefreshDatabase;

    public function test_invoice_notice_and_filter_match_deduplicated_sidebar_count(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $overdue = Invoice::factory()->create(['status' => 'sent', 'total_amount' => 110, 'due_date' => today()->subDay()]);
        $paid = Invoice::factory()->create(['status' => 'paid', 'total_amount' => 110]);
        Invoice::factory()->create(['status' => 'draft', 'total_amount' => 110]);
        Invoice::factory()->create(['status' => 'cancelled', 'total_amount' => 110]);
        $counts = app(FinanceAttention::class)->counts();
        $this->assertSame(1, $counts['overdue']);
        $this->assertSame(2, $counts['unallocated_invoices']);
        $this->assertSame(2, $counts['invoices']);
        $this->actingAs($user)->get(route('admin.invoice.index'))->assertOk()->assertSee('2 invoices need attention')->assertSee('1 overdue');
        $this->get(route('admin.invoice.index', ['status' => 'overdue']))->assertOk()->assertViewHas('invoices', fn ($rows) => $rows->pluck('id')->all() === [$overdue->id]);
        $this->get(route('admin.invoice.index', ['allocation_state' => 'not_allocated', 'status' => ['issued', 'sent', 'paid', 'overdue', 'written_off'], 'list_total_amount_min' => '0.01']))->assertOk()->assertViewHas('invoices', fn ($rows) => $rows->total() === 2 && $rows->contains('id', $paid->id));
    }

    public function test_expenses_with_supplier_defaults_or_valid_overrides_do_not_need_attention(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $missing = Expense::factory()->create(['supplier' => 'No default', 'total_amount' => 110, 'gst_amount' => 10]);
        $covered = Expense::factory()->create(['supplier' => 'With default', 'total_amount' => 110, 'gst_amount' => 10]);
        Supplier::where('id', $covered->supplier_id)->update(['category_id' => 1, 'splits' => json_encode([1 => 100])]);
        $manual = Expense::factory()->create(['supplier' => 'Manual', 'total_amount' => 110, 'gst_amount' => 10]);
        DB::table('finance_expense_splits')->insert(['expense_id' => $manual->id, 'category_id' => 1, 'cents' => 10000, 'created_at' => now(), 'updated_at' => now()]);
        $query = Expense::query();
        app(FinanceAttention::class)->unallocatedExpenses($query);
        $this->assertSame([$missing->id], $query->pluck('id')->all());
        $this->actingAs($user)->get(route('admin.expense.index', ['allocation_state' => 'not_allocated']))->assertOk()->assertSee('1 expense needs attention')->assertViewHas('expenses', fn ($rows) => $rows->total() === 1 && $rows->first()->id === $missing->id);
        DB::table('finance_expense_splits')->where('expense_id', $manual->id)->update(['cents' => 9000]);
        $query = Expense::query();
        app(FinanceAttention::class)->unallocatedExpenses($query);
        $this->assertSame(2, $query->count());
    }
}
