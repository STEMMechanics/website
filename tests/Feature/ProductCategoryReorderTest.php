<?php

namespace Tests\Feature;

use App\Models\ProductCategory;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCategoryReorderTest extends TestCase
{
    use RefreshDatabase;

    public function test_reordering_returns_json_and_renders_direct_controls(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $first = ProductCategory::create(['name' => 'First', 'slug' => 'first', 'sort_order' => 10]);
        $second = ProductCategory::create(['name' => 'Second', 'slug' => 'second', 'sort_order' => 20]);
        $this->actingAs($admin)->postJson(route('admin.shop.category.move-up', $second))->assertOk()->assertJson(['moved' => true]);
        $this->assertSame([$second->id, $first->id], ProductCategory::orderBy('sort_order')->pluck('id')->all());
        $this->postJson(route('admin.shop.category.move-up', $second))->assertOk()->assertJson(['moved' => false]);
        $this->postJson(route('admin.shop.category.move-down', $second))->assertOk()->assertJson(['moved' => true]);
        $response = $this->get(route('admin.shop.category.index', ['list_name' => 'Second']))->assertOk();
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//form[@data-list-reorder]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-list-reorder and contains(@action,"move-up")]//button[@disabled]')->length);
        $this->assertSame(0, $xpath->query('//form[@data-list-reorder]/ancestor::dialog')->length);
        $this->actingAs(User::factory()->create())->postJson(route('admin.shop.category.move-up', $second))->assertForbidden();
    }
}
