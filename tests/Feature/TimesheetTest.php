<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TimesheetTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);

        return $user;
    }

    public function test_new_entries_use_the_current_option_and_existing_entries_keep_their_rate(): void
    {
        $this->actingAs($this->admin());
        $this->postJson(route('admin.timesheet.store'), ['date' => '2026-09-01', 'hours' => 2])->assertOk();
        $id = DB::table('finance_time_entries')->value('id');
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'rate_cents' => 4000]);
        \App\Models\SiteOption::updateOrCreate(['name' => 'finance.owner-hourly-rate'], ['value' => '55.50']);
        $this->postJson(route('admin.timesheet.store'), ['date' => '2026-09-02', 'hours' => 1, 'rate' => 999])->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['date' => '2026-09-02', 'rate_cents' => 5550]);
        $this->postJson(route('admin.timesheet.store'), ['id' => $id, 'date' => '2026-09-01', 'hours' => 3])->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'rate_cents' => 4000, 'minutes' => 180]);
        $this->postJson(route('admin.finance.time'), ['date' => '2026-09-03', 'activity' => 'Preparation', 'minutes' => 60, 'rate' => 60])->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['date' => '2026-09-03', 'rate_cents' => 5550]);
    }

    public function test_owner_rate_option_validates_amounts_without_changing_planner_rates(): void
    {
        $this->actingAs($this->admin());
        $option = \App\Models\SiteOption::where('name', 'finance.owner-hourly-rate')->firstOrFail();
        $rules = DB::table('finance_pricing_versions')->pluck('rules', 'id')->all();
        foreach (['-1', 'abc', '40.123', '10001'] as $value) {
            $this->putJson(route('admin.site_option.update', $option), ['value' => $value])->assertUnprocessable();
        }
        $this->putJson(route('admin.site_option.update', $option), ['value' => '45.50'])->assertOk();
        $this->assertSame('45.50', $option->fresh()->value);
        $this->assertSame($rules, DB::table('finance_pricing_versions')->pluck('rules', 'id')->all());
    }

    public function test_existing_entry_can_explicitly_use_current_rate_and_hours_can_be_zero(): void
    {
        $owner = $this->admin();
        $id = DB::table('finance_time_entries')->insertGetId(['user_id' => $owner->id, 'date' => today()->toDateString(), 'minutes' => 300, 'rate_cents' => 6000, 'activity' => 'Business time', 'created_at' => now(), 'updated_at' => now()]);
        $migration = require database_path('migrations/2026_09_19_090000_configure_owner_timesheet_rate.php');
        $migration->up();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'rate_cents' => 6000]);
        $this->actingAs($owner)->get(route('admin.timesheet.edit', ['id' => $id]))->assertOk()->assertSee('Use current site rate');
        $payload = ['id' => $id, 'date' => today()->toDateString(), 'hours' => 5, 'reset_rate' => 1];
        $this->postJson(route('admin.timesheet.store'), $payload)->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 300, 'rate_cents' => 4000]);
        $payload['hours'] = 0;
        unset($payload['reset_rate']);
        $this->postJson(route('admin.timesheet.store'), $payload)->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 0, 'rate_cents' => 4000]);
    }

    public function test_remuneration_owing_tracks_time_while_cash_limits_what_can_be_paid(): void
    {
        $owner = $this->admin();
        DB::table('finance_time_entries')->insert(['user_id' => $owner->id, 'date' => today()->toDateString(), 'minutes' => 300, 'rate_cents' => 6000, 'activity' => 'Business time', 'created_at' => now(), 'updated_at' => now()]);
        $planner = app(\App\Services\Finance\FinancePlanner::class);
        $this->actingAs($owner)->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertOk()
            ->assertViewHas('drawingTotals', fn ($totals) => $totals['time'] === ['outstanding' => 30000, 'available' => 0]);
        DB::table('finance_settings')->where('id', 1)->update(['opening_cash_cents' => 100000]);
        DB::table('finance_categories')->where('kind', 'owner')->update(['opening_cents' => 100000]);
        $this->assertSame(['outstanding' => 30000, 'available' => 30000], $planner->drawingTotals($owner->id)['time']);
        $this->postJson(route('admin.finance.drawing'), ['amount' => 301, 'token' => (string) Str::uuid()])->assertUnprocessable();
        $id = DB::table('finance_drawings')->insertGetId(['user_id' => $owner->id, 'purpose' => 'time', 'token' => (string) Str::uuid(), 'cents' => 10000, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->assertSame(['outstanding' => 30000, 'available' => 20000], $planner->drawingTotals($owner->id)['time']);
        $this->assertSame(30000, $planner->drawingTotals($owner->id, excludingDrawing: $id)['time']['available']);
        $this->postJson(route('admin.finance.drawingStatus', $id), ['status' => 'paid', 'paid_on' => today()->toDateString()])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(['outstanding' => 20000, 'available' => 20000], $planner->drawingTotals($owner->id)['time']);
    }

    public function test_paid_drawings_protect_hours_and_rate_corrections_but_other_drawings_do_not(): void
    {
        $owner = $this->admin();
        $this->actingAs($owner);
        $id = DB::table('finance_time_entries')->insertGetId(['user_id' => $owner->id, 'date' => today()->toDateString(), 'minutes' => 300, 'rate_cents' => 6000, 'activity' => 'Business time', 'created_at' => now(), 'updated_at' => now()]);
        foreach ([['time', 'paid', 25000], ['time', 'cancelled', 50000], ['contribution', 'paid', 50000]] as [$purpose, $status, $cents]) {
            DB::table('finance_drawings')->insert(['user_id' => $owner->id, 'purpose' => $purpose, 'status' => $status, 'cents' => $cents, 'token' => (string) Str::uuid(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $payload = ['id' => $id, 'date' => today()->toDateString(), 'hours' => 0];
        $this->postJson(route('admin.timesheet.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('hours');
        $payload['hours'] = 5;
        $payload['reset_rate'] = 1;
        $this->postJson(route('admin.timesheet.store'), $payload)->assertUnprocessable()->assertJsonValidationErrors('hours');
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 300, 'rate_cents' => 6000]);
        unset($payload['reset_rate']);
        $payload['hours'] = 4.5;
        $this->postJson(route('admin.timesheet.store'), $payload)->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 270]);
        $other = $this->admin();
        $this->actingAs($other)->postJson(route('admin.timesheet.store'), ['date' => today()->toDateString(), 'hours' => 0])->assertOk();
    }

    public function test_drawings_are_in_timesheet_and_only_show_the_current_users_records(): void
    {
        $owner = $this->admin();
        $other = $this->admin();
        foreach ([$owner, $other] as $user) {
            DB::table('finance_drawings')->insert(['user_id' => $user->id, 'token' => (string) Str::uuid(), 'cents' => 1200, 'status' => 'paid', 'reference' => 'Transfer '.$user->id, 'paid_on' => today(), 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->actingAs($owner)->get(route('admin.timesheet.index', ['tab' => 'drawings']))
            ->assertOk()->assertSee('Record drawing')->assertSee('Outstanding to pay')->assertSee('Available to pay')->assertSee('Transfer '.$owner->id)->assertDontSee('Transfer '.$other->id)
            ->assertViewHas('paid', 1200)->assertViewHas('drawings', fn ($rows) => $rows->total() === 1);
        $this->get(route('admin.finance.index', ['tab' => 'drawings']))->assertRedirect(route('admin.timesheet.index', ['tab' => 'drawings']));
        $this->actingAs(User::factory()->create())->get(route('admin.timesheet.index', ['tab' => 'drawings']))->assertForbidden();
    }

    public function test_decimal_hours_and_notes_are_saved_and_only_own_entries_are_visible(): void
    {
        $owner = $this->admin();
        $other = $this->admin();
        $data = ['date' => '2026-09-01', 'hours' => 1.5, 'notes' => 'Prepare electronics'];
        $this->actingAs($owner)->postJson(route('admin.timesheet.store'), $data)->assertOk();
        $id = DB::table('finance_time_entries')->value('id');
        $this->assertDatabaseHas('finance_time_entries', ['user_id' => $owner->id, 'minutes' => 90, 'notes' => 'Prepare electronics']);
        $this->get(route('admin.timesheet.index', ['fortnight' => '2026-09-01']))->assertOk()->assertSee('Prepare electronics')->assertDontSee('Hourly target')->assertViewHas('entries', fn ($entries) => $entries->count() === 14);
        $this->get(route('admin.timesheet.edit', ['id' => $id]))->assertOk()->assertSee('Hours worked')->assertDontSee('name="activity"', false)->assertDontSee('name="rate"', false)->assertDontSee('name="workshop_id"', false);
        $this->actingAs($other)->get(route('admin.timesheet.index', ['fortnight' => '2026-09-01']))->assertOk()->assertDontSee('Prepare electronics');
        $this->get(route('admin.timesheet.edit', ['id' => $id]))->assertNotFound();
        $this->postJson(route('admin.timesheet.store'), $data + ['id' => $id])->assertNotFound();
        $this->actingAs($owner)->postJson(route('admin.timesheet.store'), array_merge($data, ['id' => $id, 'hours' => 2, 'notes' => 'Updated notes']))->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 120, 'rate_cents' => 4000, 'notes' => 'Updated notes']);
    }

    public function test_fortnight_contains_every_day_and_empty_days_can_be_edited(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 7));
        $user = $this->admin();
        $response = $this->actingAs($user)->get(route('admin.timesheet.index', ['fortnight' => '2026-08-24', 'per_page' => 10]));
        $response->assertOk()->assertViewHas('entries', fn ($entries) => $entries->count() === 14 && $entries->sum('minutes') === 0 && $entries->filter(fn ($entry) => $entry->date->isWeekend())->count() === 4);
        $date = $response->viewData('entries')->first()->date->toDateString();
        $this->get(route('admin.timesheet.edit', ['date' => $date]))->assertOk()->assertViewHas('date', $date);
        $this->postJson(route('admin.timesheet.store'), ['date' => $date, 'hours' => 0, 'notes' => 'Day off'])->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['user_id' => $user->id, 'date' => $date, 'minutes' => 0, 'notes' => 'Day off']);
        $this->get(route('admin.timesheet.edit', ['date' => '2026-09-08']))->assertSessionHasErrors('date');
    }

    public function test_calendar_day_opens_existing_entry_and_preserves_legacy_entries(): void
    {
        $this->travelTo(now()->setDate(2026, 9, 7));
        $owner = $this->admin();
        $this->actingAs($owner)->postJson(route('admin.timesheet.store'), ['date' => '2026-09-01', 'hours' => 1.5, 'notes' => 'Calendar notes'])->assertOk();
        $id = DB::table('finance_time_entries')->value('id');
        $response = $this->get(route('admin.timesheet.index', ['fortnight' => '2026-09-01', 'sort' => 'minutes', 'direction' => 'desc']))->assertOk()->assertSee('data-timesheet-calendar', false)->assertSee('Calculated amount')->assertSee('Calendar notes')->assertViewHas('earned', 6000);
        $dates = $response->viewData('entries')->pluck('date')->map->toDateString()->all();
        $ordered = $dates;
        sort($ordered);
        $this->assertSame($ordered, $dates);
        $this->get(route('admin.timesheet.edit', ['date' => '2026-09-01']))->assertOk()->assertViewHas('entry', fn ($entry) => $entry->id === $id)->assertSee('Calendar notes');
        DB::table('finance_time_entries')->insert(['user_id' => $owner->id, 'date' => '2026-09-01', 'minutes' => 30, 'rate_cents' => 3000, 'activity' => 'Preparation', 'notes' => 'Legacy record', 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('admin.timesheet.index', ['fortnight' => '2026-09-01']))->assertOk()->assertViewHas('earned', 7500)->assertViewHas('hours', 2);
        $this->get(route('admin.timesheet.edit', ['date' => '2026-09-01']))->assertOk()->assertSee('multiple existing entries')->assertViewHas('dayEntries', fn ($entries) => $entries->count() === 2);
    }

    public function test_daily_limit_is_enforced_and_reserve_drawings_do_not_prevent_time_corrections(): void
    {
        $user = $this->admin();
        $data = ['date' => '2026-09-01', 'hours' => 24];
        $this->actingAs($user)->postJson(route('admin.timesheet.store'), $data)->assertOk();
        $this->postJson(route('admin.timesheet.store'), array_merge($data, ['hours' => 0.5]))->assertUnprocessable();
        $id = DB::table('finance_time_entries')->value('id');
        DB::table('finance_drawings')->insert(['user_id' => $user->id, 'token' => (string) Str::uuid(), 'cents' => 10000, 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
        $this->postJson(route('admin.timesheet.store'), array_merge($data, ['id' => $id, 'hours' => 1]))->assertOk();
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 60]);
        $this->get(route('admin.finance.index', ['tab' => 'time']))->assertRedirect(route('admin.timesheet.index'));
        $this->actingAs(User::factory()->create())->get(route('admin.timesheet.index'))->assertForbidden();
        $this->postJson(route('admin.timesheet.store'), $data)->assertForbidden();
    }
}
