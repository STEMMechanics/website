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
        Schema::create('workshops', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('title');
            $table->string('hero_media_name');
            $table->text('content');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamp('publish_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status')->default('draft');
            $table->string('price')->nullable();
            $table->string('ages')->nullable();
            $table->string('registration')->default('none');
            $table->string('registration_data')->nullable();
            $table->foreignUuid('location_id')->nullable()->constrained('locations');
            $table->foreignUuid('user_id')->constrained('users');
            $table->timestamps();

            $table->foreign('hero_media_name')->references('name')->on('media');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workshops');
    }
};
