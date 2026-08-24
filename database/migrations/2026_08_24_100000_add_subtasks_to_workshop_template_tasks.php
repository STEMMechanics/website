<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshop_template_tasks', function (Blueprint $table): void {
            $table->json('subtasks')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('workshop_template_tasks', function (Blueprint $table): void {
            $table->dropColumn('subtasks');
        });
    }
};
