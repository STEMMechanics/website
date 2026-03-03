<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('forum_posts') && ! Schema::hasColumn('forum_posts', 'edited_at')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->timestamp('edited_at')->nullable()->after('body');
            });
        }

        if (Schema::hasTable('forum_topic_user_states') && ! Schema::hasColumn('forum_topic_user_states', 'last_emailed_at')) {
            Schema::table('forum_topic_user_states', function (Blueprint $table) {
                $table->timestamp('last_emailed_at')->nullable()->after('last_read_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('forum_topic_user_states') && Schema::hasColumn('forum_topic_user_states', 'last_emailed_at')) {
            Schema::table('forum_topic_user_states', function (Blueprint $table) {
                $table->dropColumn('last_emailed_at');
            });
        }

        if (Schema::hasTable('forum_posts') && Schema::hasColumn('forum_posts', 'edited_at')) {
            Schema::table('forum_posts', function (Blueprint $table) {
                $table->dropColumn('edited_at');
            });
        }
    }
};
