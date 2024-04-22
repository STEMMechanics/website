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
        Schema::create('mediables', function (Blueprint $table) {
            $table->id();
            $table->string('media_name');
            $table->string('mediable_id');
            $table->string('mediable_type');
            $table->index(['mediable_id', 'mediable_type'], 'media_name');
            $table->string('collection')->nullable();
            $table->timestamps();
            $table->foreign('media_name')->references('name')->on('media')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mediables');
    }
};
