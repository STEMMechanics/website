<?php

namespace Tests\Feature;

use App\Models\Expense;
use App\Models\Supplier;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RenderFragmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_list_fragment_preserves_filters_and_mobile_rows_without_rendering_navigation(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user);
        Expense::factory()->create(['supplier' => 'A long supplier & workshop name']);
        Expense::factory()->create(['supplier' => 'Other supplier']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $response = $this->get(route('admin.supplier.index', ['search' => 'A long']), ['X-SM-Fragment' => 'list:admin-suppliers']);
        $response->assertOk()->assertHeader('X-SM-Fragment', 'list:admin-suppliers')->assertSee('A long supplier &amp; workshop name', false)
            ->assertDontSee('Other supplier')->assertDontSee('<html', false)->assertDontSee('data-site-navbar', false)
            ->assertSee('sm-mobile-cards')->assertSee('data-label="Total incl GST"', false)->assertSee('data-record-editor', false);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
        DB::disableQueryLog();
        $this->assertStringNotContainsString('finance_expense_splits', $queries);
        $this->assertStringNotContainsString('update "quotes"', $queries);
        $this->get(route('admin.supplier.index'))->assertOk()->assertSee('<html', false)->assertSee('data-site-navbar', false);
    }

    public function test_record_fragment_keeps_form_tokens_and_validates_normal_permissions(): void
    {
        $supplier = Supplier::forName('Phone supplier');
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user)->get(route('admin.supplier.edit', $supplier), ['X-SM-Fragment' => 'record'])
            ->assertOk()->assertHeader('X-SM-Fragment', 'record')->assertSee('data-record-form', false)
            ->assertSee('name="_token"', false)->assertSee('name="_method"', false)->assertSee('Phone supplier')->assertDontSee('<html', false);
        $this->actingAs(User::factory()->create())->get(route('admin.supplier.edit', $supplier), ['X-SM-Fragment' => 'record'])->assertForbidden();
    }

    public function test_missing_fragment_and_invalid_header_have_safe_fallbacks(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        $this->actingAs($user)->get(route('admin.supplier.index'), ['X-SM-Fragment' => 'list:missing'])->assertStatus(409);
        $this->get(route('admin.supplier.index'), ['X-SM-Fragment' => 'list:"]//*'])->assertOk()->assertSee('<html', false);
    }
}
