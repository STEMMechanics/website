<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_player_stats', function (Blueprint $table): void {
            $table->id();
            $table->string('uuid', 64)->unique();
            $table->string('username', 80);
            $table->timestamp('captured_at')->nullable();
            $table->timestamp('fetched_at')->nullable();
            $table->json('stats');
            $table->timestamps();

            $table->index('username');
            $table->index('captured_at');
            $table->index('fetched_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_player_stats');
    }
};
