<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('minecraft_penalties', 'deleted_at')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->softDeletes()->after('lift_reason');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('minecraft_penalties', 'deleted_at')) {
            Schema::table('minecraft_penalties', function (Blueprint $table): void {
                $table->dropSoftDeletes();
            });
        }
    }
};
