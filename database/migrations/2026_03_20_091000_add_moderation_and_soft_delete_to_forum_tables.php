<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('forum_topics', function (Blueprint $table): void {
            $table->boolean('is_approved')->default(true)->after('is_locked');
            $table->foreignUuid('approved_by_user_id')->nullable()->after('is_approved')->constrained('users')->nullOnDelete();
        });

        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->boolean('is_approved')->default(true)->after('user_id');
            $table->foreignUuid('approved_by_user_id')->nullable()->after('is_approved')->constrained('users')->nullOnDelete();
            $table->timestamp('deleted_at')->nullable()->after('edited_at');
        });
    }

    public function down(): void
    {
        Schema::table('forum_posts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn([
                'is_approved',
                'deleted_at',
            ]);
        });

        Schema::table('forum_topics', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropColumn('is_approved');
        });
    }
};
