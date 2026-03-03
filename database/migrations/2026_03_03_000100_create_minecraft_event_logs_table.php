<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_event_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minecraft_account_id')->nullable()->constrained('minecraft_accounts')->nullOnDelete();
            $table->string('event', 120);
            $table->timestamp('occurred_at');
            $table->string('platform', 20)->nullable();
            $table->string('uuid', 64)->nullable();
            $table->string('username', 80)->nullable();
            $table->string('server_name', 100)->nullable();
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['event', 'occurred_at']);
            $table->index(['minecraft_account_id', 'occurred_at']);
            $table->index(['uuid', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_event_logs');
    }
};
