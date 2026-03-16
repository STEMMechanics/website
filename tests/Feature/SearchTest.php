<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_page_shows_inline_search_form_and_empty_prompt_when_query_is_blank(): void
    {
        $this->get(route('search.index'))
            ->assertOk()
            ->assertSee('Refine Search')
            ->assertSee('Search the site')
            ->assertSee('Start a new search')
            ->assertSee('Use the search bar above to find workshops across the site.');
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
            ->assertSee('Refine Search')
            ->assertSee('Search the site')
            ->assertSee('Robotics Basics')
            ->assertDontSee('Chemistry Club')
            ->assertSee('1 result');
    }
}
