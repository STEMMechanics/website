<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('tickets', 'attended_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->timestamp('attended_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('tickets', 'attended_at')) {
            return;
        }

        Schema::table('tickets', function (Blueprint $table): void {
            $table->dropColumn('attended_at');
        });
    }
};
