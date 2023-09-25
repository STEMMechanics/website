<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table) {
            $table->renameColumn('permission', 'security');
        });

        DB::table('media')
            ->where('security', '!=', '')
            ->update(['security' => DB::raw("CONCAT('permission:', security)")]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('media')
        ->where(function ($query) {
            $query->where('security', 'NOT LIKE', 'permission:%');
        })
        ->update(['security' => '']);

        DB::table('media')
        ->where('security', 'LIKE', 'permission:%')
        ->update(['security' => DB::raw("SUBSTRING(security, 11)")]);

        Schema::table('media', function (Blueprint $table) {
            $table->renameColumn('security', 'permission');
        });
    }
};
