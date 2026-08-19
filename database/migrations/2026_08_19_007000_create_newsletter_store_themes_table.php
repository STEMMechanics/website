<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('newsletter_store_themes', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('title');
            $table->text('intro')->nullable();
            $table->json('category_slugs');
            $table->string('match_type')->default('random');
            $table->unsignedSmallInteger('match_days')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('newsletter_store_themes')->insert([
            ['name' => 'New Kit Arrivals', 'title' => 'New kits arrived', 'intro' => 'Fresh project ideas for curious makers.', 'category_slugs' => json_encode(['kits']), 'match_type' => 'created_within', 'match_days' => 7, 'is_active' => true, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Grab Extras', 'title' => 'Grab extras', 'intro' => 'Stock up on useful materials and parts for the next build.', 'category_slugs' => json_encode(['materials', 'parts']), 'match_type' => 'random', 'match_days' => null, 'is_active' => true, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Back in Stock', 'title' => 'Back in stock', 'intro' => 'Popular workshop favourites are available again.', 'category_slugs' => json_encode(['kits', 'materials', 'parts']), 'match_type' => 'restocked_within', 'match_days' => 14, 'is_active' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Recently Updated', 'title' => 'Recently updated', 'intro' => 'Take another look at these refreshed products and project ideas.', 'category_slugs' => json_encode(['kits', 'materials', 'parts']), 'match_type' => 'updated_within', 'match_days' => 14, 'is_active' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Featured Picks', 'title' => 'Worth discovering', 'intro' => 'A few engaging ways to build, test and create.', 'category_slugs' => json_encode(['kits', 'materials', 'parts']), 'match_type' => 'featured', 'match_days' => null, 'is_active' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('newsletter_store_themes');
    }
};
