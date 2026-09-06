<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->uuid('event_uuid')->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table) {
            $table->dropUnique(['event_uuid']);
            $table->dropColumn('event_uuid');
        });
    }
};
