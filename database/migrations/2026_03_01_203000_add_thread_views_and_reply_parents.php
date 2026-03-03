<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table): void {
            $table->unsignedInteger('view_count')->default(0)->after('is_pinned');
        });

        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->foreignUuid('parent_forum_post_id')->nullable()->after('forum_topic_id')->constrained('forum_posts')->nullOnDelete();
            $table->index(['forum_topic_id', 'parent_forum_post_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->dropForeign(['parent_forum_post_id']);
            $table->dropIndex(['forum_topic_id', 'parent_forum_post_id', 'created_at']);
            $table->dropColumn('parent_forum_post_id');
        });

        Schema::table('forum_topics', function (Blueprint $table): void {
            $table->dropColumn('view_count');
        });
    }
};
