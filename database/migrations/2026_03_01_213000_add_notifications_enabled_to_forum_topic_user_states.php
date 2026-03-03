<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topic_user_states', function (Blueprint $table): void {
            $table->boolean('notifications_enabled')->nullable()->after('last_emailed_at');
        });

        DB::table('forum_topic_user_states')
            ->join('forum_posts', function ($join): void {
                $join->on('forum_posts.forum_topic_id', '=', 'forum_topic_user_states.forum_topic_id')
                    ->on('forum_posts.user_id', '=', 'forum_topic_user_states.user_id');
            })
            ->whereNull('forum_topic_user_states.notifications_enabled')
            ->update([
                'forum_topic_user_states.notifications_enabled' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('forum_topic_user_states', function (Blueprint $table): void {
            $table->dropColumn('notifications_enabled');
        });
    }
};
