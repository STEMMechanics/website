<?php
namespace Tests\Feature;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;
class OwnerContributionTest extends TestCase
{
    use RefreshDatabase;
    public function test_dashboard_distinguishes_monthly_income_from_overview_and_excludes_repayable_contributions(): void
    {
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-09 12:00:00'));
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        \App\Models\Payment::factory()->create(['received_on' => '2026-09-07 11:16:00', 'total_amount' => 610.50]);
        \App\Models\Payment::factory()->create(['received_on' => '2026-08-20 09:17:00', 'total_amount' => 8748.95]);
        $dashboard = app(\App\Services\AdminDashboardService::class);
        $before = $dashboard->build('month');

        $this->postJson(route('admin.timesheet.contribution.store'), [
            'token' => (string) Str::uuid(), 'date' => today()->toDateString(),
            'amount' => 9500, 'reference' => 'Repayable personal funding', 'splits' => [5 => 9500],
        ])->assertOk();

        $month = collect($dashboard->build('month')['cards'])->firstWhere('title', 'Finance');
        $overview = collect($dashboard->build()['cards'])->firstWhere('title', 'Finance');
        $this->assertSame(collect($before['cards'])->firstWhere('title', 'Finance'), $month);
        $this->assertSame('$610.50', collect($month['metrics'])->firstWhere('label', 'Income')['current']);
        $this->assertSame('$9,359.45', collect($overview['metrics'])->firstWhere('label', 'Income')['current']);
        $this->travelBack();
    }

    public function test_contributions_fund_cost_centres_without_income_or_double_counting(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        $data = ['token' => (string) Str::uuid(), 'date' => today()->toDateString(), 'amount' => 100, 'reference' => 'Personal funding', 'splits' => [5 => 100]];
        $planner = app(FinancePlanner::class);
        $before = $planner->cash();
        $this->get(route('admin.timesheet.contribution.edit'))->assertOk()->assertSee('Remaining');
        $this->postJson(route('admin.timesheet.contribution.store'), array_replace($data, ['splits' => [5 => 99]]))->assertUnprocessable();
        $this->postJson(route('admin.timesheet.contribution.store'), $data)->assertOk();
        $this->postJson(route('admin.timesheet.contribution.store'), $data)->assertOk();
        $this->assertDatabaseCount('finance_owner_contributions', 1);
        $after = $planner->cash();
        $this->assertSame($before['cash'] + 10000, $after['cash']);
        $this->assertSame($before['reserves'][5] + 10000, $after['reserves'][5]);
        $this->assertSame($before['gst'], $after['gst']);
        $this->assertSame($before['available'], $after['available']);
        $this->get(route('admin.cost-centre.show', 5))->assertOk()->assertSee('Personal funding');
        $this->get(route('admin.timesheet.index', ['tab' => 'contributions']))->assertOk()->assertSee('Personal funding');
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings', 'purpose' => 'contribution']))->assertOk()->assertViewHas('earned', 10000);
        $this->actingAs(User::factory()->create())->postJson(route('admin.timesheet.contribution.store'), $data)->assertForbidden();
    }
    public function test_capital_return_does_not_reduce_timesheet_or_wages_balance(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 20000]);
        $this->postJson(route('admin.timesheet.contribution.store'), ['token' => (string) Str::uuid(), 'date' => today()->toDateString(), 'amount' => 100, 'reference' => 'Capital', 'splits' => [5 => 100]])->assertOk();
        $planner = app(FinancePlanner::class);
        $wages = $planner->cash()['reserves'][6];
        $this->post(route('admin.finance.drawing'), ['purpose' => 'contribution', 'amount' => 101, 'token' => (string) Str::uuid()])->assertSessionHasErrors('amount');
        $this->post(route('admin.finance.drawing'), ['purpose' => 'contribution', 'amount' => 40, 'token' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $drawing = DB::table('finance_drawings')->first();
        $this->post(route('admin.finance.drawingStatus', $drawing->id), ['status' => 'paid', 'paid_on' => today()->toDateString(), 'reference' => 'Returned capital'])->assertSessionHasNoErrors();
        $this->assertSame($wages, $planner->cash()['reserves'][6]);
        $this->assertSame(26000, $planner->cash()['cash']);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings', 'purpose' => 'contribution']))->assertOk()->assertViewHas('paid', 4000);
        $this->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()->assertViewHas('paid', 0);
        $this->postJson(route('admin.timesheet.store'), ['date' => today()->toDateString(), 'hours' => 0])->assertOk();
    }
}
