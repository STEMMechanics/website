<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('posts', 'articles');

        // Update permissions to use articles instead of posts
        DB::table('permissions')->select('id', 'permission')->where('permission', 'admin/posts')->update(['permission' => 'admin/articles']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('articles', 'posts');

        // Update permissions to use posts instead of articles
        DB::table('permissions')->select('id', 'permission')->where('permission', 'admin/articles')->update(['permission' => 'admin/posts']);
    }
};
