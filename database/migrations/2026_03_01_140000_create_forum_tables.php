<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_categories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('read_group_slug')->nullable();
            $table->string('write_group_slug')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('forum_topics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('forum_category_id')->constrained('forum_categories')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('last_post_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_pinned')->default(false);
            $table->timestamp('last_post_at')->nullable();
            $table->timestamps();

            $table->unique(['forum_category_id', 'slug']);
            $table->index(['forum_category_id', 'is_pinned', 'last_post_at']);
        });

        Schema::create('forum_posts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('forum_topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->longText('body');
            $table->timestamp('edited_at')->nullable();
            $table->timestamps();

            $table->index(['forum_topic_id', 'created_at']);
        });

        Schema::create('forum_post_reactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('forum_post_id')->constrained('forum_posts')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamps();

            $table->unique(['forum_post_id', 'user_id']);
            $table->index(['forum_post_id', 'type']);
        });

        Schema::create('forum_topic_user_states', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('forum_topic_id')->constrained('forum_topics')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('last_read_at')->nullable();
            $table->timestamp('last_emailed_at')->nullable();
            $table->timestamps();

            $table->unique(['forum_topic_id', 'user_id']);
            $table->index(['user_id', 'last_read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_topic_user_states');
        Schema::dropIfExists('forum_post_reactions');
        Schema::dropIfExists('forum_posts');
        Schema::dropIfExists('forum_topics');
        Schema::dropIfExists('forum_categories');
    }
};
