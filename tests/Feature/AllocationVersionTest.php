<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\PricingVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AllocationVersionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    private function version(string $name, string $date, int $rate, bool $makeDefault = false): int
    {
        $this->post(route('admin.finance.pricing'), ['name' => $name, 'effective_from' => $date, 'make_default' => $makeDefault, 'rules' => [['category_id' => 1, 'basis' => 'hour', 'rate' => $rate]], 'public' => [10, 20, 30, 40], 'organisation' => [10, 20, 30, 40], 'return_to' => 'versions'])->assertSessionHasNoErrors()->assertRedirect(route('admin.cost-centre.allocations', ['tab' => 'versions']));
        return (int) DB::table('finance_pricing_versions')->where('name', $name)->value('id');
    }

    private function invoice(): Invoice
    {
        $invoice = Invoice::factory()->create(['issue_date' => '2026-09-07']);
        InvoiceLine::factory()->create(['invoice_id' => $invoice->id, 'kind' => 'workshop', 'quantity' => 30, 'details_json' => ['workshop' => ['hours' => 2, 'seats' => 15, 'venue_supplied' => true]]]);
        return $invoice;
    }

    public function test_archiving_preserves_history_excludes_choices_and_orders_archived_plans_last(): void
    {
        $this->admin();
        $old = $this->version('2025 plan', '2025-07-01', 20);
        $current = $this->version('2026 plan', '2026-07-01', 30, true);
        $invoice = $this->invoice();
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['version_id' => $old, 'use_defaults' => 1])->assertOk();
        $this->post(route('admin.cost-centre.version.archive', $old), ['archived' => 1])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('finance_pricing_versions', ['id' => $old, 'archived' => true]);
        $this->assertSame($old, app(InvoiceAllocation::class)->context($invoice)['version']->id);
        $this->get(route('admin.cost-centre.allocations'))->assertOk()->assertSeeInOrder(['2026 plan', '2025 plan', 'Archived']);
        $freshInvoice = $this->invoice();
        $this->get(route('admin.invoice.allocation.edit', $freshInvoice))->assertOk()->assertDontSee('2025 plan');
        $this->getJson(route('admin.invoice.bulk-allocation.preview', ['invoice_ids' => [$freshInvoice->id], 'version_id' => $old]))->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.default-version'), ['version_id' => $old])->assertUnprocessable();
        $this->postJson(route('admin.cost-centre.version.archive', $current), ['archived' => 1])->assertUnprocessable();
        $this->post(route('admin.cost-centre.version.archive', $old), ['archived' => 0])->assertSessionHasNoErrors();
        $this->get(route('admin.invoice.allocation.edit', $freshInvoice))->assertOk()->assertSee('2025 plan');
        $this->actingAs(User::factory()->create())->postJson(route('admin.cost-centre.version.archive', $old), ['archived' => 1])->assertForbidden();
    }

    public function test_unused_plan_can_be_deleted_but_default_used_and_saved_revisions_are_protected(): void
    {
        $this->admin();
        $unused = $this->version('Unused', '2026-07-01', 20);
        $this->delete(route('admin.cost-centre.version.destroy', $unused))->assertSessionHasNoErrors()->assertRedirect(route('admin.cost-centre.allocations'));
        $this->assertDatabaseMissing('finance_pricing_versions', ['id' => $unused]);
        $this->get(route('admin.cost-centre.allocations'))->assertOk()->assertSee('SM.alert(', false)->assertSee('Allocation plan deleted.')->assertDontSee('bg-emerald-50 p-4 text-emerald-800', false);

        $default = $this->version('Default', '2026-07-01', 20, true);
        $this->deleteJson(route('admin.cost-centre.version.destroy', $default))->assertUnprocessable();
        $used = $this->version('Used', '2026-07-01', 20);
        $invoice = $this->invoice();
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['version_id' => $used, 'use_defaults' => 1])->assertOk();
        $this->deleteJson(route('admin.cost-centre.version.destroy', $used))->assertUnprocessable();
        $workshopPlan = $this->version('Workshop plan', '2026-07-01', 20);
        \App\Models\Location::factory()->create();
        $media = \App\Models\Media::factory()->create(['user_id' => auth()->id()]);
        \App\Models\Workshop::factory()->create(['pricing_version_id' => $workshopPlan, 'user_id' => auth()->id(), 'hero_media_name' => $media->name]);
        $this->deleteJson(route('admin.cost-centre.version.destroy', $workshopPlan))->assertUnprocessable();
        $snapshot = $this->version('Saved revision', '2026-07-01', 20);
        DB::table('finance_pricing_versions')->where('id', $snapshot)->update(['is_snapshot' => true]);
        $this->deleteJson(route('admin.cost-centre.version.destroy', $snapshot))->assertNotFound();
        foreach ([$default, $used, $workshopPlan, $snapshot] as $id) {
            $this->assertDatabaseHas('finance_pricing_versions', ['id' => $id]);
        }
        $this->actingAs(User::factory()->create())->deleteJson(route('admin.cost-centre.version.destroy', $used))->assertForbidden();
    }

    public function test_popup_creates_cost_based_version_without_participant_price_fields(): void
    {
        $this->admin();
        $this->get(route('admin.cost-centre.allocations'))->assertOk()
            ->assertViewIs('admin.cost-centre.versions')->assertSee('data-record-editor', false)
            ->assertDontSee('name="travel_price"', false);
        $this->get(route('admin.cost-centre.allocations', ['tab' => 'editor']))->assertOk()
            ->assertSee('data-record-form', false)->assertSee('name="travel_price"', false)
            ->assertDontSee('Price per participant, including GST')->assertDontSee('name="effective_from"', false)->assertSee('Set as default');
        $this->postJson(route('admin.finance.pricing'), [
            'name' => 'Calculated costs',
            'rules' => [['category_id' => 1, 'basis' => 'hour', 'rate' => 60]],
            'travel_price' => 34, 'travel_free_minutes' => 30, 'make_default' => true,
        ])->assertOk()->assertJsonPath('message', 'Allocation plan saved. Existing allocations are unchanged.');
        $version = DB::table('finance_pricing_versions')->where('name', 'Calculated costs')->first();
        $this->assertSame([], json_decode($version->prices, true)['public']);
        $invoice = $this->invoice();
        $preview = app(FinancePlanner::class)->preview([
            'version_id' => $version->id, 'invoice_ids' => [$invoice->id],
            'participants' => 15, 'hours' => 2, 'travel_minutes' => 45, 'venue_supplied' => true,
        ]);
        // Two hours at $60 + GST; a plan without travel cost rules has no travel charge.
        $this->assertSame(13200, $preview['rows'][0]['suggested_price_cents']);
        $this->assertSame(12000, $preview['rows'][0]['targets'][1]);
    }

    public function test_default_and_explicit_historical_version_do_not_change_existing_allocations(): void
    {
        $this->admin();
        $old = $this->version('2025-26', '2025-07-01', 20);
        $current = $this->version('2026-27', '2026-07-01', 60);
        $this->post(route('admin.cost-centre.default-version'), ['version_id' => $current])->assertSessionHasNoErrors();
        $invoice = $this->invoice();
        $this->assertSame($current, PricingVersion::forDate('2026-09-07')->id);
        $this->get(route('admin.invoice.allocation.edit', [$invoice, 'version_id' => $old]))->assertOk()->assertViewHas('allocation', fn ($allocation) => $allocation['version']->id === $old && $allocation['targets'][1] === 4000);
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['version_id' => $old, 'use_defaults' => 1])->assertOk();
        $allocation = app(InvoiceAllocation::class)->context($invoice);
        $this->assertSame($old, $allocation['budget']->pricing_version_id);
        $this->assertSame(4000, $allocation['targets'][1]);
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['budget_id' => $allocation['budget']->id, 'version_id' => $current, 'use_defaults' => 1])->assertUnprocessable();
        $this->assertSame($old, app(InvoiceAllocation::class)->context($invoice)['version']->id);
    }

    public function test_bulk_historical_preview_uses_line_details_and_skips_existing_allocations(): void
    {
        $this->admin();
        $old = $this->version('Historical', '2025-07-01', 20);
        $invoice = $this->invoice();
        $preview = app(FinancePlanner::class)->preview(['version_id' => $old, 'invoice_ids' => [$invoice->id]]);
        $this->assertSame(4000, $preview['rows'][0]['targets'][1]);
        $this->assertNull($preview['rows'][0]['warning']);
        app(FinancePlanner::class)->apply($preview, auth()->id(), [0]);
        $again = app(FinancePlanner::class)->preview(['version_id' => $old, 'invoice_ids' => [$invoice->id]]);
        $this->assertStringContainsString('Already allocated', $again['rows'][0]['warning']);
        $this->get(route('admin.cost-centre.allocations'))->assertOk();
    }

    public function test_editing_plan_preserves_existing_rules_and_default_and_rejects_stale_edit(): void
    {
        $this->admin();
        $old = $this->version('2026-27', '2026-07-01', 60, true);
        $invoice = $this->invoice();
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['version_id' => $old, 'use_defaults' => 1])->assertOk();
        $this->get(route('admin.cost-centre.allocations', ['tab' => 'editor', 'edit_id' => $old]))
            ->assertOk()->assertSee('Save')->assertSee('value="2026-27"', false);
        $edit = ['edit_id' => $old, 'name' => '2026-27 updated', 'effective_from' => '2026-07-01',
            'rules' => [['category_id' => 1, 'basis' => 'hour', 'rate' => 80]], 'travel_price' => 40];
        $this->postJson(route('admin.finance.pricing'), $edit)->assertOk();
        $new = DB::table('finance_pricing_versions')->where('name', '2026-27 updated')->first();
        $this->assertDatabaseHas('finance_pricing_versions', ['id' => $old, 'is_snapshot' => true]);
        $this->assertDatabaseHas('finance_settings', ['id' => 1, 'default_pricing_version_id' => $new->id]);
        app(InvoiceAllocation::class)->sync($invoice, auth()->id());
        $context = app(InvoiceAllocation::class)->context($invoice);
        $this->assertSame($old, $context['version']->id);
        $this->assertSame(12000, $context['targets'][1]);
        $this->assertSame(16000, app(InvoiceAllocation::class)->context($this->invoice(), $new->id)['targets'][1]);
        $this->postJson(route('admin.finance.pricing'), $edit)->assertUnprocessable();
        $this->get(route('admin.cost-centre.allocations'))->assertViewHas('versions', fn ($versions) => !$versions->contains('id', $old));
        $this->actingAs(User::factory()->create())->postJson(route('admin.finance.pricing'), $edit)->assertForbidden();
    }

    public function test_suppliable_rules_are_saved_and_applied_to_lines_and_shared_workshops(): void
    {
        $this->admin();
        $this->postJson(route('admin.finance.pricing'), ['name' => 'Supplied materials', 'make_default' => true,
            'rules' => [['category_id' => 1, 'basis' => 'hour', 'rate' => 60, 'suppliable' => 1],
                ['category_id' => 2, 'basis' => 'participant', 'rate' => 5, 'suppliable' => 1]],
        ])->assertOk();
        $version = DB::table('finance_pricing_versions')->where('name', 'Supplied materials')->first();
        $this->assertTrue(json_decode($version->rules, true)[1]['suppliable']);
        $invoice = $this->invoice();
        $line = $invoice->lines()->first();
        $this->get(route('admin.invoice.allocation.edit', $invoice))->assertOk()->assertSee('Consumables supplied');
        $this->postJson(route('admin.invoice.allocation.store', $invoice), ['version_id' => $version->id, 'use_defaults' => 1,
            'line_details' => [$line->id => ['workshop_hours' => 2, 'workshop_seats' => 15, 'supplied_categories' => [2 => 1]]],
        ])->assertOk();
        $context = app(InvoiceAllocation::class)->context($invoice);
        $this->assertSame(12000, $context['targets'][1]);
        $this->assertSame(0, $context['targets'][2] ?? 0);
        $this->assertTrue($line->fresh()->details_json['workshop']['supplied_categories'][2]);
        $ticketInvoice = Invoice::factory()->create();
        $ticket = \App\Models\Ticket::factory()->create(['invoice_id' => $ticketInvoice->id, 'status' => \App\Models\Ticket::STATUS_PAID]);
        $this->postJson(route('admin.invoice.allocation.store', $ticketInvoice), ['version_id' => $version->id,
            'use_defaults' => 1, 'supplied_categories' => [1 => 1, 2 => 0],
        ])->assertOk();
        app(InvoiceAllocation::class)->sync($ticketInvoice, auth()->id());
        $shared = app(InvoiceAllocation::class)->context($ticketInvoice);
        $this->assertTrue($shared['assumptions']['supplied_categories'][1]);
        $this->assertSame(0, $shared['targets'][1] ?? 0);
        $this->assertSame(500, $shared['targets'][2]);
    }

    public function test_versions_page_default_selection_and_access_control(): void
    {
        $this->admin();
        $old = $this->version('2025-26', '2025-07-01', 20);
        $current = $this->version('2026-27', '2026-09-07', 60);
        $this->post(route('admin.cost-centre.default-version'), ['version_id' => $old])->assertSessionHasNoErrors();
        $this->assertSame($old, PricingVersion::forDate('2026-09-07')->id);
        $this->get(route('admin.cost-centre.allocations', ['tab' => 'versions', 'template_id' => $current]))->assertOk()->assertSee('Make default')->assertSee('2025-26')->assertViewHas('templateVersion', fn ($version) => $version->id === $current);
        $future = $this->version('2027-28', '2027-07-01', 80, true);
        $this->assertDatabaseHas('finance_settings', ['id' => 1, 'default_pricing_version_id' => $future]);
        $this->post(route('admin.cost-centre.default-version'), ['version_id' => $future])->assertSessionHasNoErrors();
        $this->assertSame($future, PricingVersion::forDate('2026-09-07')->id);
        $this->assertSame($future, PricingVersion::forDate('2027-07-01')->id);
        $this->actingAs(User::factory()->create())->post(route('admin.cost-centre.default-version'), ['version_id' => $old])->assertForbidden();
    }
}
