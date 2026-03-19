<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->boolean('is_topic_starter')->default(false)->after('user_id');
        });

        DB::table('forum_topics')
            ->select(['id', 'user_id'])
            ->orderBy('id')
            ->chunk(100, function ($topics): void {
                foreach ($topics as $topic) {
                    $starterPostId = DB::table('forum_posts')
                        ->where('forum_topic_id', $topic->id)
                        ->orderByRaw('case when user_id = ? then 0 else 1 end', [$topic->user_id])
                        ->orderBy('created_at')
                        ->orderBy('id')
                        ->value('id');

                    if ($starterPostId === null) {
                        continue;
                    }

                    DB::table('forum_posts')
                        ->where('id', $starterPostId)
                        ->update(['is_topic_starter' => true]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->dropColumn('is_topic_starter');
        });
    }
};
