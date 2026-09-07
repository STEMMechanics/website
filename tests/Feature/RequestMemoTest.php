<?php

namespace Tests\Feature;

use App\Models\SiteOption;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\RequestMemo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RequestMemoTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_and_permissions_reuse_queries_but_refresh_after_writes_and_rollback(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->assertTrue($user->isAdmin());
        $this->assertTrue($user->isAdmin());
        $this->assertSame('first', SiteOption::value('missing-test-setting', 'first'));
        $this->assertSame('second', SiteOption::value('missing-test-setting', 'second'));
        $this->assertCount(2, DB::getQueryLog());
        DB::disableQueryLog();
        DB::table('user_groups')->where('user_id', $user->id)->delete();
        $this->assertFalse($user->isAdmin());
        DB::beginTransaction();
        DB::table('site_options')->insert(['name' => 'missing-test-setting', 'value' => 'new']);
        $this->assertSame('new', SiteOption::value('missing-test-setting'));
        DB::rollBack();
        $this->assertNull(SiteOption::value('missing-test-setting'));
        $first = app(RequestMemo::class);
        $this->app->forgetScopedInstances();
        $this->assertNotSame($first, app(RequestMemo::class));
    }

    public function test_filter_options_are_loaded_once_per_request(): void
    {
        $user = User::factory()->create();
        UserGroup::create(['user_id' => $user->id, 'slug' => 'admin']);
        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->actingAs($user)->get(route('admin.supplier.index'))->assertOk();
        $queries = collect(DB::getQueryLog())->where('query', 'select "name", "id" from "finance_categories" where "kind" = ? order by "name" asc');
        DB::disableQueryLog();
        $this->assertCount(1, $queries);
    }
}
