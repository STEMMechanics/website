<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            $table->boolean('workplan_checked')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('workshops', fn (Blueprint $table) => $table->dropColumn('workplan_checked'));
    }
};
