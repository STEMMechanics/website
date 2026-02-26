<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_attendances', function (Blueprint $table): void {
            $table->string('child_name')->nullable()->after('source');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_attendances', function (Blueprint $table): void {
            $table->dropColumn('child_name');
        });
    }
};
