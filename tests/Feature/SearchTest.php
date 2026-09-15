<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\Workshop;
use App\Support\ShopAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_scopes_default_to_both_and_can_select_either_or_neither(): void
    {
        SiteOption::query()->updateOrCreate(['name' => ShopAvailability::PUBLIC_ENABLED_OPTION], ['value' => '1']);
        $this->get(route('search.index', ['q' => 'robot']))->assertOk()
            ->assertViewHas('searchProducts', true)->assertViewHas('searchWorkshops', true);
        $this->get(route('search.index', ['q' => 'robot', 'include_products' => 1, 'include_workshops' => 0]))
            ->assertOk()->assertViewHas('workshops', null)->assertViewHas('searchProducts', true);
        $this->get(route('search.index', ['q' => 'robot', 'include_products' => 0, 'include_workshops' => 1]))
            ->assertOk()->assertViewHas('products', null)->assertViewHas('searchWorkshops', true);
        $this->get(route('search.index', ['q' => 'robot', 'include_products' => 0, 'include_workshops' => 0]))
            ->assertOk()->assertViewHas('products', null)->assertViewHas('workshops', null)
            ->assertViewHas('searchScopeError', true)->assertSeeText('Select at least one: Store or Workshops.');
    }

    public function test_product_and_workshop_refinements_do_not_filter_each_other(): void
    {
        SiteOption::query()->updateOrCreate(['name' => ShopAvailability::PUBLIC_ENABLED_OPTION], ['value' => '1']);
        $product = Product::factory()->create(['title' => 'Robotics Kit', 'status' => Product::STATUS_ACTIVE, 'price' => 10]);
        $location = Location::factory()->create();
        $user = User::factory()->create();
        $hero = Media::factory()->create(['name' => 'scope-test.png', 'mime_type' => 'image/png', 'user_id' => $user->id]);
        $workshop = Workshop::factory()->create([
            'title' => 'Robotics Workshop', 'location_id' => $location->id, 'user_id' => $user->id,
            'hero_media_name' => $hero->name, 'publish_at' => now()->subDay(), 'status' => 'open', 'is_hidden' => false,
        ]);
        $this->get(route('search.index', ['q' => 'robotics', 'search_products_price_min' => 100]))
            ->assertOk()->assertViewHas('products', fn ($results) => $results->total() === 0)
            ->assertViewHas('workshops', fn ($results) => $results->pluck('id')->all() === [$workshop->id]);
        $this->get(route('search.index', ['q' => 'robotics', 'search_workshops_starts_at_min' => now()->addYears(3)->toDateString()]))
            ->assertOk()->assertViewHas('workshops', fn ($results) => $results->total() === 0)
            ->assertViewHas('products', fn ($results) => $results->pluck('id')->all() === [$product->id]);
        $this->get(route('search.index', ['q' => 'robotics', 'include_products' => 0, 'include_workshops' => 1, 'search_products_price_min' => 'invalid']))
            ->assertOk()->assertViewHas('products', null)
            ->assertViewHas('workshops', fn ($results) => $results->total() === 1);
    }

    public function test_the_shared_search_replaces_legacy_section_searches_and_keeps_scopes_when_paging(): void
    {
        SiteOption::query()->updateOrCreate(['name' => ShopAvailability::PUBLIC_ENABLED_OPTION], ['value' => '1']);
        Product::factory()->count(8)->create(['title' => 'Battery holder', 'status' => Product::STATUS_ACTIVE]);
        $response = $this->get(route('search.index', [
            'q' => 'holder', 'search_products_search' => 'cats', 'include_products' => 1, 'include_workshops' => 0,
        ]))->assertOk()->assertViewHas('products', fn ($results) => $results->total() === 8)
            ->assertDontSee('name="search_products_search"', false)
            ->assertDontSee('name="search_workshops_search"', false)
            ->assertSee('include_products=1', false)->assertSee('include_workshops=0', false);
        $this->assertSame(1, substr_count($response->getContent(), 'id="results-search-query"'));
    }

    public function test_store_categories_offer_the_shared_popup_with_store_scope_on_desktop_and_mobile(): void
    {
        SiteOption::query()->updateOrCreate(['name' => ShopAvailability::PUBLIC_ENABLED_OPTION], ['value' => '1']);
        $category = ProductCategory::factory()->create();
        $product = Product::factory()->create(['status' => Product::STATUS_ACTIVE]);
        $product->categories()->attach($category);
        $response = $this->get(route('shop.index'))->assertOk();
        $this->assertSame(2, substr_count($response->getContent(), "\$dispatch('open-site-search', { scope: 'store' })"));
        $response->assertSee('name="include_products"', false)->assertSee('name="include_workshops"', false);
    }

    public function test_search_page_shows_inline_search_form_and_empty_prompt_when_query_is_blank(): void
    {
        SiteOption::query()->updateOrCreate([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
        ], [
            'value' => '1',
        ]);

        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('Search the site')
            ->assertSee('Start a new search')
            ->assertSee('Use the search bar above to find workshops and store products across the site.');
    }

    public function test_search_page_shows_matching_workshops_and_keeps_the_refinement_form_visible(): void
    {
        $location = Location::factory()->create();
        $user = User::factory()->create();
        $hero = Media::factory()->create([
            'name' => 'search-test.png',
            'mime_type' => 'image/png',
            'user_id' => $user->id,
        ]);

        Workshop::factory()->create([
            'title' => 'Robotics Basics',
            'content' => '<p>Build your first robot.</p>',
            'location_id' => $location->id,
            'user_id' => $user->id,
            'hero_media_name' => $hero->name,
            'publish_at' => now()->subDay(),
            'status' => 'open',
            'is_hidden' => false,
        ]);

        Workshop::factory()->create([
            'title' => 'Chemistry Club',
            'content' => '<p>Hands-on chemistry activities.</p>',
            'location_id' => $location->id,
            'user_id' => $user->id,
            'hero_media_name' => $hero->name,
            'publish_at' => now()->subDay(),
            'status' => 'open',
            'is_hidden' => false,
        ]);

        $this->get(route('search.index', ['q' => 'robotics']))
            ->assertOk()
            ->assertSee('Search the site')
            ->assertSee('Robotics Basics')
            ->assertDontSee('Chemistry Club')
            ->assertSee('1 result');
    }

    public function test_search_page_shows_matching_store_products_when_the_store_is_enabled(): void
    {
        SiteOption::query()->updateOrCreate([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
        ], [
            'value' => '1',
        ]);

        Product::factory()->create([
            'title' => 'Bundle Pack',
            'subtitle' => '25 pack',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
        ]);

        $this->get(route('search.index', ['q' => 'bundle']))
            ->assertOk()
            ->assertSeeText('Store Products')
            ->assertSeeText('Bundle Pack')
            ->assertSeeText('25 pack');
    }

    public function test_search_page_matches_product_search_terms(): void
    {
        SiteOption::query()->updateOrCreate([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
        ], [
            'value' => '1',
        ]);

        Product::factory()->create([
            'title' => 'Wooden Craft Sticks',
            'search_terms' => 'popsicle sticks, ice block sticks',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 7.95,
        ]);

        $this->get(route('search.index', ['q' => 'popsicle']))
            ->assertOk()
            ->assertSeeText('Wooden Craft Sticks');

        $this->get(route('shop.index', ['search' => 'popsicle']))
            ->assertOk()
            ->assertSeeText('Wooden Craft Sticks');
    }

    public function test_product_page_includes_search_terms_as_meta_keywords(): void
    {
        SiteOption::query()->updateOrCreate([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
        ], [
            'value' => '1',
        ]);

        $product = Product::factory()->create([
            'title' => 'Wooden Craft Sticks',
            'search_terms' => 'popsicle sticks, ice block sticks',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSee('<meta name="keywords" content="popsicle sticks, ice block sticks">', false);
    }

    public function test_search_page_does_not_search_store_products_when_the_store_is_disabled(): void
    {
        SiteOption::query()->updateOrCreate([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
        ], [
            'value' => '0',
        ]);

        Product::factory()->create([
            'title' => 'Bundle Pack',
            'subtitle' => '25 pack',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
        ]);

        $this->get(route('search.index', ['q' => 'bundle']))
            ->assertOk()
            ->assertDontSeeText('Store Products')
            ->assertDontSeeText('Bundle Pack')
            ->assertDontSeeText('25 pack');
    }

    public function test_search_page_shows_only_section_headers_and_zero_results_when_nothing_matches(): void
    {
        SiteOption::query()->updateOrCreate([
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
        ], [
            'value' => '1',
        ]);

        $this->get(route('search.index', ['q' => 'nothing-match']))
            ->assertOk()
            ->assertSeeText('Store Products')
            ->assertSeeText('0 results')
            ->assertSeeText('Workshops')
            ->assertDontSee('No results found', false);
    }
}
