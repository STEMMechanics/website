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
        $this->assertDatabaseHas('finance_time_entries', ['id' => $id, 'minutes' => 120, 'rate_cents' => 6000, 'notes' => 'Updated notes']);
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
        $response = $this->get(route('admin.timesheet.index', ['fortnight' => '2026-09-01', 'sort' => 'minutes', 'direction' => 'desc']))->assertOk()->assertSee('data-timesheet-calendar', false)->assertSee('Calculated amount')->assertSee('Calendar notes')->assertViewHas('earned', 9000);
        $dates = $response->viewData('entries')->pluck('date')->map->toDateString()->all();
        $ordered = $dates;
        sort($ordered);
        $this->assertSame($ordered, $dates);
        $this->get(route('admin.timesheet.edit', ['date' => '2026-09-01']))->assertOk()->assertViewHas('entry', fn ($entry) => $entry->id === $id)->assertSee('Calendar notes');
        DB::table('finance_time_entries')->insert(['user_id' => $owner->id, 'date' => '2026-09-01', 'minutes' => 30, 'rate_cents' => 3000, 'activity' => 'Preparation', 'notes' => 'Legacy record', 'created_at' => now(), 'updated_at' => now()]);
        $this->get(route('admin.timesheet.index', ['fortnight' => '2026-09-01']))->assertOk()->assertViewHas('earned', 10500)->assertViewHas('hours', 2);
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
