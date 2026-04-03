<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (! Schema::hasColumn('workshops', 'summary')) {
                $table->text('summary')->nullable()->after('content');
            }
        });
    }

    public function down(): void
    {
        Schema::table('workshops', function (Blueprint $table): void {
            if (Schema::hasColumn('workshops', 'summary')) {
                $table->dropColumn('summary');
            }
        });
    }
};
