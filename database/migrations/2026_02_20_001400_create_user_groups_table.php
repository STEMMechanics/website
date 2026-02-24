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
        Schema::create('user_groups', function (Blueprint $table): void {
            $table->id();
            $table->uuid('user_id');
            $table->string('slug', 80);

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'slug']);
            $table->index('slug');
        });

        $adminUserIds = DB::table('users')
            ->where('admin', 1)
            ->pluck('id');

        foreach ($adminUserIds as $userId) {
            DB::table('user_groups')->insertOrIgnore([
                'user_id' => $userId,
                'slug' => 'admin',
            ]);
        }

        if (Schema::hasColumn('users', 'admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropColumn('admin');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('users', 'admin')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->boolean('admin')->default(false);
            });
        }

        $adminUserIds = DB::table('user_groups')
            ->where('slug', 'admin')
            ->pluck('user_id')
            ->all();

        if ($adminUserIds !== []) {
            DB::table('users')
                ->whereIn('id', $adminUserIds)
                ->update(['admin' => 1]);
        }

        Schema::dropIfExists('user_groups');
    }
};
