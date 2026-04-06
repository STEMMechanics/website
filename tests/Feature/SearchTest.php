<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Product;
use App\Models\Media;
use App\Models\SiteOption;
use App\Models\User;
use App\Models\Workshop;
use App\Support\ShopAvailability;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

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
