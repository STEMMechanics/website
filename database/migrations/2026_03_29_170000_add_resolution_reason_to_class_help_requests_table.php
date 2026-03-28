<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('class_help_requests', 'resolution_reason')) {
            Schema::table('class_help_requests', function (Blueprint $table): void {
                $table->text('resolution_reason')->nullable()->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('class_help_requests', 'resolution_reason')) {
            Schema::table('class_help_requests', function (Blueprint $table): void {
                $table->dropColumn('resolution_reason');
            });
        }
    }
};
