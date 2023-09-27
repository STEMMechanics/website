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
            $table->string('security_type');
            $table->renameColumn('permission', 'security_data');
        });

        DB::table('media')
            ->where('security_data', '!=', '')
            ->update(['security_type' => 'permission']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('media')
        ->where('security_type', '!=', 'permission')
        ->update(['security_data' => '']);

        Schema::table('media', function (Blueprint $table) {
            $table->renameColumn('security_data', 'permission');
            $table->dropColumn('security_type');
        });
    }
};
