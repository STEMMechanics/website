<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Supplier;
use App\Services\Finance\FinanceAttention;
use App\Support\AdminBadgeCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminBadgeCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Real commits are needed here; each test gets its own in-memory database.
        $this->artisan('migrate:fresh')->assertSuccessful();
    }

    private function nextRequestCounts(): array
    {
        $this->app->forgetInstance(AdminBadgeCache::class);

        return app(FinanceAttention::class)->counts();
    }

    public function test_counts_are_shared_across_requests_and_supplier_changes_invalidate_them(): void
    {
        $expense = Expense::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $this->assertSame(1, $this->nextRequestCounts()['expenses']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertSame(1, $this->nextRequestCounts()['expenses']);
        $this->assertCount(0, DB::getQueryLog());
        DB::disableQueryLog();
        Supplier::findOrFail($expense->supplier_id)->update(['splits' => [1 => 100]]);
        $this->assertSame(0, $this->nextRequestCounts()['expenses']);
    }

    public function test_direct_allocation_writes_publish_only_after_commit_and_rollback_keeps_old_counts(): void
    {
        $expense = Expense::factory()->create(['total_amount' => 110, 'gst_amount' => 10]);
        $this->assertSame(1, $this->nextRequestCounts()['expenses']);
        $generation = Cache::get('admin-badges:v1:finance:generation');
        DB::beginTransaction();
        DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => 1, 'cents' => 10000]);
        $this->assertSame(0, app(FinanceAttention::class)->counts()['expenses']);
        $this->assertSame($generation, Cache::get('admin-badges:v1:finance:generation'));
        $this->assertSame(1, (new AdminBadgeCache)->remember('finance', fn () => $this->fail('Uncommitted data must not replace the shared cache'))['expenses']);
        DB::rollBack();
        $this->assertSame(1, $this->nextRequestCounts()['expenses']);
        $this->assertSame($generation, Cache::get('admin-badges:v1:finance:generation'));
        DB::transaction(fn () => DB::table('finance_expense_splits')->insert(['expense_id' => $expense->id, 'category_id' => 1, 'cents' => 10000]));
        $this->assertNotSame($generation, Cache::get('admin-badges:v1:finance:generation'));
        $this->assertSame(0, $this->nextRequestCounts()['expenses']);
        DB::table('finance_expense_splits')->where('expense_id', $expense->id)->update(['cents' => 9000]);
        $this->assertSame(1, $this->nextRequestCounts()['expenses']);
    }

    public function test_overdue_counts_roll_over_at_midnight_and_expire_after_one_minute(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(23)->addMinutes(59)->addSeconds(40));
        $invoice = Invoice::factory()->create(['status' => 'sent', 'due_date' => today(), 'total_amount' => 110]);
        $this->assertSame(0, $this->nextRequestCounts()['overdue']);
        $this->travel(30)->seconds();
        $this->assertSame(1, $this->nextRequestCounts()['overdue']);
        // An external writer bypasses application invalidation; TTL remains the fallback.
        DB::connection()->getPdo()->exec("UPDATE invoices SET status = 'cancelled' WHERE id = ".(int) $invoice->id);
        $this->assertSame(1, $this->nextRequestCounts()['overdue']);
        $this->travel(61)->seconds();
        $this->assertSame(0, $this->nextRequestCounts()['overdue']);
    }

    public function test_operations_badges_invalidate_on_direct_writes(): void
    {
        $calls = 0;
        $resolve = function () use (&$calls) {
            return ++$calls;
        };
        $this->assertSame(1, app(AdminBadgeCache::class)->remember('operations', $resolve));
        $this->app->forgetInstance(AdminBadgeCache::class);
        $this->assertSame(1, app(AdminBadgeCache::class)->remember('operations', $resolve));
        DB::table('inbound_sms')->where('id', 0)->update(['acknowledged_at' => now()]);
        $this->assertSame(2, app(AdminBadgeCache::class)->remember('operations', $resolve));
    }
    public function test_blank_badge_store_uses_the_default_cache_for_workshop_changes(): void
    {
        config(['cache.admin_badges_store' => '', 'cache.default' => 'array']);
        $cache = app(\App\Support\AdminBadgeCache::class);
        $this->assertSame(1, $cache->remember('finance', fn () => 1));
        $workshop = \App\Models\Ticket::factory()->create()->workshop;
        $workshop->update(['ends_at' => now()]);
        $this->assertSame(2, $cache->remember('finance', fn () => 2));
    }

}
