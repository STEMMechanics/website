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
        Schema::table('workshops', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('is_private');
        });

        DB::table('workshops')
            ->where('status', 'hidden')
            ->update([
                'is_hidden' => true,
                'status' => 'open',
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('workshops')
            ->where('is_hidden', true)
            ->where('status', 'open')
            ->update([
                'status' => 'hidden',
            ]);

        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};
