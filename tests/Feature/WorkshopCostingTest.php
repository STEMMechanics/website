<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserGroup;
use App\Services\Finance\WorkshopCosting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Tests\TestCase;

class WorkshopCostingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
    }

    public function test_links_open_a_single_page_pdf_inline_for_admins_without_creating_allocations(): void
    {
        $this->actingAs(User::factory()->create());
        foreach (['index', 'pdf'] as $action) {
            $this->get(route('admin.workshop-costing.'.$action))->assertForbidden();
        }
        $this->admin();
        $budgets = DB::table('finance_budgets')->count();
        foreach (['index', 'pdf'] as $action) {
            $pdf = $this->get(route('admin.workshop-costing.'.$action));
            $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
            $this->assertStringContainsString('inline;', $pdf->headers->get('content-disposition'));
            $this->assertStringStartsWith('%PDF-', $pdf->getContent());
            $reader = new Fpdi;
            $this->assertSame(1, $reader->setSourceFile(StreamReader::createByString($pdf->getContent())));
        }
        $this->assertSame($budgets, DB::table('finance_budgets')->count());
    }

    public function test_costs_use_distinct_bases_supplied_items_and_billable_travel(): void
    {
        $version = (object) ['rules' => json_encode([
            ['category_id' => 1, 'basis' => 'workshop', 'rate_cents' => 1000],
            ['category_id' => 2, 'basis' => 'participant', 'rate_cents' => 500],
            ['category_id' => 3, 'basis' => 'hour', 'rate_cents' => 6000],
            ['category_id' => 4, 'basis' => 'venue_hour', 'rate_cents' => 4500],
            ['category_id' => 5, 'basis' => 'travel', 'rate_cents' => 3400, 'suppliable' => true],
        ]), 'prices' => json_encode(['rounding_step' => 100, 'travel_rounding_step' => 100, 'travel_free_minutes' => 30])];
        $input = ['hours' => 2, 'participants' => 15, 'travel_hours' => 0.5, 'supplied_categories' => [4 => true]];
        $result = app(WorkshopCosting::class)->calculate($version, $input);
        $this->assertSame(27300, $result['cost']);
        $this->assertSame(24000, $result['workshop']['gross']);
        $this->assertSame(7600, $result['travel']['gross']);
        $this->assertSame(31600, $result['gross']);
        $this->assertSame(1600, $result['ticket']);
        $other = app(WorkshopCosting::class)->calculate($version, array_replace($input, ['hours' => 1, 'participants' => 30]));
        $this->assertSame(28800, $other['cost']);
        $supplied = app(WorkshopCosting::class)->calculate($version, array_replace($input, ['supplied_categories' => [4 => true, 5 => true]]));
        $this->assertSame(20500, $supplied['cost']);
        $this->assertSame(0, $supplied['travel']['gross']);
    }

    public function test_guide_uses_baseline_attendance_and_venue_supplied_school_prices(): void
    {
        $version = (object) ['rules' => json_encode([
            ['category_id' => 1, 'basis' => 'hour', 'rate_cents' => 4000, 'suppliable' => true, 'venue_default' => true],
            ['category_id' => 2, 'basis' => 'hour', 'rate_cents' => 6000],
            ['category_id' => 3, 'basis' => 'participant', 'rate_cents' => 500],
            ['category_id' => 4, 'basis' => 'travel', 'rate_cents' => 3400],
        ]), 'prices' => json_encode(['pricing_participants' => 10, 'rounding_step' => 50, 'travel_rounding_step' => 100])];
        $guide = app(WorkshopCosting::class)->guide($version, [1 => 'Venue', 2 => 'Wages', 3 => 'Consumables', 4 => 'Travel']);
        $this->assertSame(['standard' => 1650, 'school' => 1250], $guide['durations'][1]);
        $this->assertSame(['standard' => 4950, 'school' => 3200], $guide['durations'][4]);
        $this->assertSame(3800, $guide['travelRate']);
        $this->assertSame(5, $guide['regions'][7]['units']);
        $this->assertStringContainsString('excluded for school/library', $guide['breakdown']['Venue'][0]);
        $this->assertArrayNotHasKey('Travel', $guide['breakdown']);
    }

    public function test_no_active_plan_redirects_to_allocation_plans(): void
    {
        $this->admin();
        DB::table('finance_pricing_versions')->update(['archived' => true]);
        $this->get(route('admin.workshop-costing.index'))->assertRedirect(route('admin.cost-centre.allocations'));
    }

    public function test_unrounded_invoice_units_show_shortfall_and_ticket_rounds_up(): void
    {
        $version = (object) ['rules' => json_encode([['category_id' => 1, 'basis' => 'workshop', 'rate_cents' => 1000]]), 'prices' => '{}'];
        $result = app(WorkshopCosting::class)->calculate($version, ['hours' => 1, 'participants' => 3, 'travel_hours' => 0, 'supplied_categories' => []]);
        $this->assertSame(999, $result['workshop']['net']);
        $this->assertSame(100, $result['tax']);
        $this->assertSame(1099, $result['gross']);
        $this->assertSame(-1, $result['balance']);
        $this->assertSame(367, $result['ticket']);
    }
}
