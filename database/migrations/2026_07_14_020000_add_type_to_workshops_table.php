<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->string('type', 20)->nullable()->after('summary');
        });

        DB::table('workshops')
            ->whereNotNull('location_id')
            ->update(['type' => 'physical']);

        DB::table('workshops')
            ->whereNull('location_id')
            ->update(['type' => 'online']);
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->dropColumn('type');
        });
    }
};
