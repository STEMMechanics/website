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
        Schema::create('analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 20)->default('page_view');
            $table->string('session_token', 64);
            $table->string('visitor_hash', 64)->nullable();
            $table->string('path');
            $table->string('route_name')->nullable();
            $table->string('workshop_id')->nullable();
            $table->string('search_term')->nullable();
            $table->string('referrer_host')->nullable();
            $table->string('http_method', 10)->default('GET');
            $table->timestamp('created_at')->useCurrent();

            $table->index('created_at');
            $table->index('session_token');
            $table->index('visitor_hash');
            $table->index('workshop_id');
            $table->index('event_type');
            $table->index('search_term');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analytics_events');
    }
};
