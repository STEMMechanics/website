<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomPageFallbackTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('custom_pages')) {
            Schema::create('custom_pages', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('title', 200);
                $table->string('path', 200)->unique();
                $table->json('aliases')->nullable();
                $table->longText('content');
                $table->string('hero_media_name')->nullable();
                $table->boolean('show_mast')->default(false);
                $table->string('seo_title', 200)->nullable();
                $table->string('seo_description', 255)->nullable();
                $table->boolean('seo_noindex')->default(false);
                $table->boolean('is_published')->default(true);
                $table->uuid('user_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table): void {
                $table->string('name')->primary();
                $table->string('path')->nullable();
                $table->string('disk')->nullable();
                $table->timestamps();
            });
        }

        DB::table('custom_pages')->whereIn('path', ['/custom-page', '/custom-page-with-alias', '/stemcraft/join'])->delete();
    }

    protected function tearDown(): void
    {
        DB::table('custom_pages')->whereIn('path', ['/custom-page', '/custom-page-with-alias', '/stemcraft/join'])->delete();

        parent::tearDown();
    }

    public function test_generic_fallback_checks_custom_pages_before_404(): void
    {
        DB::table('custom_pages')->insert([
            'id' => (string) Str::uuid(),
            'title' => 'Custom Page',
            'path' => '/custom-page',
            'content' => '<p>Custom content</p>',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/custom-page');

        $response->assertOk();
        $response->assertSee('Custom Page');
    }

    public function test_first_class_stemcraft_routes_are_not_served_from_custom_pages(): void
    {
        DB::table('custom_pages')->insert([
            'id' => (string) Str::uuid(),
            'title' => 'Join STEMCraft',
            'path' => '/stemcraft/join',
            'content' => '<p>Old custom content</p>',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/stemcraft/join');

        $response->assertOk();
        $response->assertSee('Get Ready to Build Online');
        $response->assertSee('play.stemcraft.com.au');
        $response->assertDontSee('Old custom content', false);
    }

    public function test_custom_page_alias_redirects_to_canonical_path(): void
    {
        DB::table('custom_pages')->insert([
            'id' => (string) Str::uuid(),
            'title' => 'Aliased Custom Page',
            'path' => '/custom-page-with-alias',
            'aliases' => json_encode(['/custom-page-alias']),
            'content' => '<p>Rules content</p>',
            'is_published' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->get('/custom-page-alias');

        $response->assertRedirect('/custom-page-with-alias');
        $response->assertStatus(301);
    }
}
