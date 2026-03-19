<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->string('email')->nullable()->change();
            $table->string('password')->nullable()->after('email_verified_at');
            $table->foreignUuid('parent_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->boolean('child_can_create_forum_topics')->default(true)->after('password');
            $table->boolean('child_can_reply_in_forum')->default(true)->after('child_can_create_forum_topics');
            $table->boolean('child_forum_topic_requires_approval')->default(false)->after('child_can_reply_in_forum');
            $table->boolean('child_forum_reply_requires_approval')->default(false)->after('child_forum_topic_requires_approval');
            $table->boolean('child_parent_notified_on_forum_topics')->default(false)->after('child_forum_reply_requires_approval');
            $table->boolean('child_parent_notified_on_forum_replies')->default(false)->after('child_parent_notified_on_forum_topics');
            $table->timestamp('anonymized_at')->nullable()->after('remember_token');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_user_id');
            $table->dropColumn([
                'password',
                'child_can_create_forum_topics',
                'child_can_reply_in_forum',
                'child_forum_topic_requires_approval',
                'child_forum_reply_requires_approval',
                'child_parent_notified_on_forum_topics',
                'child_parent_notified_on_forum_replies',
                'anonymized_at',
            ]);
            $table->string('email')->nullable(false)->change();
        });
    }
};
