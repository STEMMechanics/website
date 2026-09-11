<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class FinanceReserveDrawingTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        UserGroup::create(['user_id' => $this->owner->id, 'slug' => 'admin']);
        $this->actingAs($this->owner);
    }

    private function cash(): array
    {
        return app(FinancePlanner::class)->cash();
    }

    private function prepare(string $purpose, int $amount): int
    {
        $token = (string) Str::uuid();
        $this->post(route('admin.finance.drawing'), compact('purpose', 'amount', 'token'))->assertSessionHasNoErrors();

        return (int) DB::table('finance_drawings')->where('token', $token)->value('id');
    }

    private function pay(int $id): void
    {
        $this->post(route('admin.finance.drawingStatus', $id), ['status' => 'paid', 'paid_on' => today()->toDateString(), 'reference' => 'Owner bank payment'])->assertSessionHasNoErrors();
    }

    private function contribute(): void
    {
        $this->postJson(route('admin.timesheet.contribution.store'), ['token' => (string) Str::uuid(), 'date' => today()->toDateString(), 'amount' => 200, 'reference' => 'Owner capital', 'splits' => [7 => 200]])->assertOk();
    }

    public function test_owner_can_receive_120_remuneration_and_130_contribution_repayment(): void
    {
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 12000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 12000]);
        $this->contribute();
        $this->assertSame(0, $this->cash()['available']);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()->assertSee('Owner remuneration')
            ->assertViewHas('drawingTotals', fn ($totals) => $totals['time']['available'] === 12000 && $totals['contribution']['available'] === 0);
        $this->post(route('admin.finance.drawing'), ['purpose' => 'contribution', 'amount' => 1, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->pay($this->prepare('time', 120));
        $this->assertSame(0, $this->cash()['reserves'][6]);
        $this->assertSame(20000, app(FinancePlanner::class)->drawingTotals($this->owner->id)['contribution']['outstanding']);
        $this->postJson(route('admin.cost-centre.transfer'), ['from_category_id' => 7, 'category_id' => 'cash', 'amount' => 130, 'reason' => 'Release assets to repay owner'])->assertOk();
        $this->assertSame(13000, $this->cash()['available']);
        $this->assertSame(20000, $this->cash()['cash']);
        $this->assertSame(7000, $this->cash()['reserves'][7]);
        $this->assertArrayNotHasKey('', $this->cash()['reserves']);
        $this->assertDatabaseHas('finance_fund_transfers', ['from_category_id' => 7, 'category_id' => null, 'cents' => 13000, 'reason' => 'Release assets to repay owner']);
        $this->get(route('admin.cost-centre.show', 7))->assertOk()->assertSee('Available business cash')->assertSee('Release assets to repay owner');
        $repayment = $this->prepare('contribution', 130);
        $this->assertSame(0, $this->cash()['available']);
        $this->post(route('admin.finance.drawing'), ['purpose' => 'contribution', 'amount' => 1, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->pay($repayment);
        $this->pay($repayment); // Completing again does not pay twice.
        $this->assertSame(25000, (int) DB::table('finance_drawings')->where('status', 'paid')->sum('cents'));
        $this->assertSame(7000, $this->cash()['cash']);
        $this->assertSame(0, $this->cash()['reserves'][6]);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings', 'purpose' => 'contribution']))->assertOk()
            ->assertViewHas('paid', 13000)->assertViewHas('drawingTotals', fn ($totals) => $totals['contribution']['outstanding'] === 7000 && $totals['time']['available'] === 0);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()->assertViewHas('paid', 12000);
        $ledger = app(FinancePlanner::class)->costCentreLedger(DB::table('finance_categories')->where('id', 6)->first());
        $this->assertSame(-12000, $ledger->firstWhere('type', 'drawing')['amount']);
        $this->postJson(route('admin.timesheet.store'), ['date' => today()->toDateString(), 'hours' => 0])->assertOk();
    }

    public function test_both_transfer_endpoints_support_business_cash_and_reject_invalid_destinations(): void
    {
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 10000]);
        foreach (['admin.cost-centre.transfer', 'admin.finance.transfer'] as $route) {
            $data = ['amount' => 20, 'reason' => 'Reserve movement'];
            $this->post(route($route), $data + ['category_id' => 1])->assertSessionHasNoErrors();
            $this->assertSame(8000, $this->cash()['available']);
            $this->post(route($route), $data + ['from_category_id' => 1, 'category_id' => 2])->assertSessionHasNoErrors();
            $this->assertSame(0, $this->cash()['reserves'][1]);
            $this->assertSame(2000, $this->cash()['reserves'][2]);
            $this->post(route($route), $data + ['from_category_id' => 2, 'category_id' => 'cash'])->assertSessionHasNoErrors();
            $this->assertSame(10000, $this->cash()['available']);
            foreach ([[null, 'cash'], [1, 1], [1, 6], [1, 'contribution'], [1, 999999], [1, 'cash']] as [$from, $to]) {
                $this->post(route($route), $data + ['from_category_id' => $from, 'category_id' => $to])->assertSessionHasErrors();
            }
            $this->post(route($route), ['category_id' => 1, 'amount' => 101, 'reason' => 'Exceeds cash'])->assertSessionHasErrors('amount');
        }
        $this->assertDatabaseCount('finance_fund_transfers', 6);
        $this->get(route('admin.cost-centre.transfer.edit'))->assertOk()->assertSee('value="cash"', false)->assertDontSee('Owner contributions');
    }

    public function test_pending_remuneration_reserves_funds_globally_without_consuming_business_cash_twice(): void
    {
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 15000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 12000]);
        $id = $this->prepare('time', 120);
        $this->assertSame(3000, $this->cash()['available']);
        $other = User::factory()->create();
        $this->assertSame(0, app(FinancePlanner::class)->drawingTotals($other->id)['time']['available']);
        $this->postJson(route('admin.cost-centre.transfer'), ['category_id' => 1, 'amount' => 31, 'reason' => 'Cannot spend pending pay'])->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.transfer'), ['category_id' => 1, 'amount' => 30, 'reason' => 'Use free cash'])->assertOk();
        $this->pay($id);
        $this->assertSame(3000, $this->cash()['cash']);
        $this->assertSame(0, $this->cash()['available']);
        $this->assertSame(0, $this->cash()['reserves'][6]);
    }

    public function test_payment_rechecks_cash_and_reserve_and_cancellation_releases_pending_funds(): void
    {
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 12000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 12000]);
        $this->post(route('admin.finance.drawing'), ['amount' => 121, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $id = $this->prepare('time', 120);
        foreach (['finance_settings' => ['opening_cash_cents' => 11999], 'finance_categories' => ['opening_cents' => 11999]] as $table => $values) {
            DB::table($table)->where('id', $table === 'finance_settings' ? 1 : 6)->update($values);
            $this->post(route('admin.finance.drawingStatus', $id), ['status' => 'paid', 'paid_on' => today()->toDateString(), 'reference' => 'Insufficient funds'])->assertSessionHasErrors('amount');
            $this->assertDatabaseHas('finance_drawings', ['id' => $id, 'status' => 'pending']);
            DB::table($table)->where('id', $table === 'finance_settings' ? 1 : 6)->update(array_map(fn () => 12000, $values));
        }
        $this->post(route('admin.finance.drawingStatus', $id), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(12000, app(FinancePlanner::class)->drawingTotals($this->owner->id)['time']['available']);
        $this->assertSame(12000, $this->cash()['cash']);
    }

    public function test_both_drawing_purposes_can_be_pending_and_paid_in_either_order(): void
    {
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 12000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 12000]);
        $this->contribute();
        $this->postJson(route('admin.cost-centre.transfer'), ['from_category_id' => 7, 'category_id' => 'cash', 'amount' => 130, 'reason' => 'Release capital'])->assertOk();
        $time = $this->prepare('time', 120);
        $contribution = $this->prepare('contribution', 130);
        $this->assertSame(0, $this->cash()['available']);
        $this->pay($contribution);
        $this->pay($time);
        $this->assertSame(7000, $this->cash()['cash']);
        $this->assertSame(0, $this->cash()['reserves'][6]);
        $this->assertSame(0, $this->cash()['available']);
    }

    public function test_remuneration_requires_actual_cash_even_with_a_funded_reserve(): void
    {
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 5000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 12000]);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()
            ->assertViewHas('drawingTotals', fn ($totals) => $totals['time']['available'] === 5000);
        $this->post(route('admin.finance.drawing'), ['amount' => 51, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->pay($this->prepare('time', 50));
        $this->assertSame(0, $this->cash()['cash']);
        $this->assertSame(7000, $this->cash()['reserves'][6]);
    }

    public function test_contribution_pending_cash_cannot_be_reallocated_or_paid_after_cash_is_spent(): void
    {
        $this->contribute();
        $this->postJson(route('admin.cost-centre.transfer'), ['from_category_id' => 7, 'category_id' => 'cash', 'amount' => 200, 'reason' => 'Repayment'])->assertOk();
        $id = $this->prepare('contribution', 200);
        $this->postJson(route('admin.cost-centre.transfer'), ['category_id' => 1, 'amount' => 1, 'reason' => 'Committed cash'])->assertUnprocessable();
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => -1]);
        $this->post(route('admin.finance.drawingStatus', $id), ['status' => 'paid', 'paid_on' => today()->toDateString(), 'reference' => 'Short by a cent'])->assertSessionHasErrors('amount');
        $this->post(route('admin.finance.drawingStatus', $id), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame(19999, app(FinancePlanner::class)->drawingTotals($this->owner->id)['contribution']['available']);
    }
}
