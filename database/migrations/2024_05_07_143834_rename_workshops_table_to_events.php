<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::rename('workshops', 'events');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['workshop_id']);
            $table->renameColumn('workshop_id', 'event_id');
            $table->foreign('event_id')->references('id')->on('events');

        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::rename('events', 'workshops');

        Schema::table('tickets', function (Blueprint $table) {
            $table->dropForeign(['event_id']);
            $table->renameColumn('event_id', 'workshop_id');
            $table->foreign('workshops_id')->references('id')->on('workshops');
        });
    }
};
