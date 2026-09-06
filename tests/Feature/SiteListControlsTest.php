<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SiteListControlsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        return $user;
    }

    public function test_registered_list_fields_are_real_non_sensitive_columns(): void
    {
        foreach (config('listings') as $route => $definition) {
            if (!isset($definition['model'])) continue;
            $model = new $definition['model'];
            foreach ($definition['fields'] as $field => $options) {
                if (isset($options['sort_sql'])) {
                    $this->assertFalse($options['filter']);
                    continue;
                }
                $this->assertTrue(Schema::hasColumn($model->getTable(), $field), "$route: $field is missing");
                $this->assertNotContains($field, ['password', 'tfa_secret', 'access_token', 'private_code']);
            }
        }
    }

    public function test_sorting_precedes_pagination_and_wildcards_match_whole_patterns(): void
    {
        $this->admin();
        foreach (range(1, 15) as $number) Location::create(['name' => sprintf('Place %02d', $number), 'address' => 'Cairns']);
        $response = $this->get(route('admin.location.index', ['list_sort' => 'name', 'list_direction' => 'desc']))->assertOk();
        $this->assertSame('Place 15', $response->viewData('locations')->first()->name);
        $response->assertSee('aria-sort="descending"', false)->assertSee('Filter results');
        $response = $this->get(route('admin.location.index', ['list_name' => '* 0?', 'list_sort' => 'name']))->assertOk();
        $this->assertSame(9, $response->viewData('locations')->total());
        $response->assertSee('Name: * 0?');
    }

    public function test_sort_columns_and_directions_are_validated(): void
    {
        $this->admin();
        $this->getJson(route('admin.location.index', ['list_sort' => 'password', 'list_direction' => 'desc; drop table users']))
            ->assertUnprocessable()->assertJsonValidationErrors(['list_sort', 'list_direction']);
        $this->getJson(route('admin.user.index', ['list_created_at_min' => 'invalid']))->assertUnprocessable()->assertJsonValidationErrors('list_created_at_min');
    }

    public function test_ghost_preset_is_an_editable_filter_and_excludes_verified_accounts(): void
    {
        $this->admin();
        $ghost = User::factory()->unverified()->create();
        $response = $this->get(route('admin.user.index', ['account_state' => 'ghost']))->assertOk();
        $this->assertSame([$ghost->id], $response->viewData('users')->pluck('id')->all());
        $response->assertSee('Accounts: Unverified users')->assertDontSee('name="show_ghost"', false);
    }
    public function test_registered_index_pages_render_and_accept_sorting(): void
    {
        $this->withoutExceptionHandling();
        $this->admin();
        foreach (config('listings') as $name => $definition) {
            $route = app('router')->getRoutes()->getByName($name);
            if (in_array($name, ['admin.server.square-events', 'admin.server.square-webhooks'])) continue; // Group keys use MySQL JSON functions.
            if (!$route || $route->parameterNames() !== [] || !isset($definition['model'])) continue;
            $response = $this->get(route($name, ['list_sort' => array_key_first($definition['fields']), 'list_direction' => 'desc']));
            if ($name === 'shop.index' && $response->status() === 503) continue;
            $this->assertSame(200, $response->status(), $name);
            $response->assertSee('Sort results');
            $dom = new \DOMDocument;
            @$dom->loadHTML($response->getContent());
            $xpath = new \DOMXPath($dom);
            foreach ($xpath->query('//dialog[@data-list-dialog]//*[self::input or self::select][@name]') as $input) {
                $this->assertFalse(ctype_digit($input->getAttribute('name')), $name.': filter field lost its name');
            }
        }
    }

    public function test_pagination_and_forms_preserve_nested_filters(): void
    {
        $this->admin();
        foreach (range(1, 15) as $number) Location::create(['name' => 'Place '.$number]);
        $response = $this->get(route('admin.location.index', ['list_sort' => 'name', 'list_direction' => 'desc', 'context' => ['ids' => [12, 34]]]));
        $response->assertOk()->assertSee('name="context[ids][0]" value="12"', false)
            ->assertSee('list_direction=desc', false)->assertSee('page=2', false);
    }

    public function test_account_filters_keep_invoice_ownership_and_include_both_date_boundaries(): void
    {
        $user = User::factory()->create();
        $own = \App\Models\Invoice::factory()->create(['user_id' => $user->id, 'status' => 'issued', 'issue_date' => '2026-06-15']);
        \App\Models\Invoice::factory()->create(['user_id' => User::factory()->create()->id, 'status' => 'issued', 'issue_date' => '2026-06-15']);
        \App\Models\Invoice::factory()->create(['user_id' => $user->id, 'status' => 'issued', 'issue_date' => '2026-06-16']);
        $response = $this->actingAs($user)->get(route('account.invoice.index', ['list_issue_date_min' => '2026-06-15', 'list_issue_date_max' => '2026-06-15', 'list_sort' => 'total_amount']));
        $response->assertOk();
        $this->assertSame([$own->id], $response->viewData('invoices')->pluck('id')->all());
    }

    public function test_aggregate_filters_and_sorting_run_before_pagination(): void
    {
        foreach (['A', 'A', 'B', 'B', 'B', 'C'] as $name) Location::create(['name' => $name]);
        request()->query->replace(['analytics_top_pages_views_min' => 2, 'analytics_top_pages_sort' => 'views', 'analytics_top_pages_direction' => 'desc']);
        $query = \Illuminate\Support\Facades\DB::table('locations')->selectRaw('name as path, COUNT(*) as views, COUNT(*) as sessions')->groupBy('name');
        $results = (new \App\Services\SiteListControls('analytics_top_pages'))->reportQuery($query)->paginate(1);
        $this->assertSame(2, $results->total());
        $this->assertSame('B', $results->first()->path);
    }

    public function test_scoped_collection_filters_do_not_apply_other_table_filters(): void
    {
        request()->query->replace(['bas_expenses_total_amount_min' => 10, 'bas_expenses_sort' => 'total_amount', 'bas_expenses_direction' => 'desc', 'bas_payments_bas_total_amount_min' => 999]);
        $rows = collect([['supplier' => 'A', 'total_amount' => 5], ['supplier' => 'B', 'total_amount' => 20], ['supplier' => 'C', 'total_amount' => 10]]);
        $results = (new \App\Services\SiteListControls('bas_expenses'))->applyCollection($rows);
        $this->assertSame(['B', 'C'], $results->pluck('supplier')->all());
    }

    public function test_users_match_the_collection_mast_counted_presets_and_table_surface(): void
    {
        $this->admin();
        User::factory()->unverified()->create();
        $response = $this->get(route('admin.user.index', ['account_state' => 'all']))->assertOk();
        $html = $response->getContent();
        $this->assertLessThan(strpos($html, 'data-dynamic-list="admin-user"'), strpos($html, '>Create User</a>'));
        $response->assertSee('aria-label="Breadcrumb"', false)->assertSee('sm-mast-actions', false)
            ->assertSee('rounded-xl border border-slate-200 bg-white', false)->assertDontSee('sm-ui-toolbar', false)
            ->assertSee('Status')->assertSee('Email address not yet verified')->assertDontSee('italic text-gray-700', false);
        $document = new \DOMDocument;
        @$document->loadHTML($html);
        $xpath = new \DOMXPath($document);
        foreach (['Verified users' => 1, 'Unverified users' => 1, 'All users' => 2] as $label => $count) {
            $link = $xpath->query('//nav[@aria-label="Preset views"]/a[@aria-label="'.$label.'"]')->item(0);
            $this->assertNotNull($link);
            $this->assertSame($label.' '.$count, preg_replace('/\s+/', ' ', trim($link->textContent)));
        }
    }

}
