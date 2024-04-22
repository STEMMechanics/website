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
        Schema::create('posts', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('hero_media_name');
            $table->string('status')->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->string('title');
            $table->longText('content');
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
        Schema::dropIfExists('posts');
    }
};
