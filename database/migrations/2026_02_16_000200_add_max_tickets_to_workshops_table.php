<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('workshops', 'max_tickets')) {
            return;
        }

        Schema::table('workshops', function (Blueprint $table) {
            $table->unsignedInteger('max_tickets')->nullable()->after('registration_data');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('workshops', 'max_tickets')) {
            return;
        }

        Schema::table('workshops', function (Blueprint $table) {
            $table->dropColumn('max_tickets');
        });
    }
};
