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
        Schema::create('events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('location');
            $table->string('address')->nullable();
            $table->timestamp('start_at')->useCurrent();
            $table->timestamp('end_at')->useCurrent();
            $table->timestamp('publish_at')->useCurrent()->nullable();
            $table->string('status')->default('draft');
            $table->string('registration_type');
            $table->string('registration_data')->nullable();
            $table->uuid('hero');
            $table->text('content')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
