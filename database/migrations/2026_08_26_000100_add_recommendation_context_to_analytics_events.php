<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->string('source_workshop_id')->nullable()->after('workshop_id');
            $table->string('recommendation_placement', 40)->nullable()->after('source_workshop_id');
            $table->index(['event_type', 'recommendation_placement']);
        });
    }

    public function down(): void
    {
        Schema::table('analytics_events', function (Blueprint $table): void {
            $table->dropIndex(['event_type', 'recommendation_placement']);
            $table->dropColumn(['source_workshop_id', 'recommendation_placement']);
        });
    }
};
