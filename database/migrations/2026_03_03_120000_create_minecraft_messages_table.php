<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('minecraft_account_id')->nullable()->constrained('minecraft_accounts')->nullOnDelete();
            $table->timestamp('occurred_at');
            $table->string('message_type', 40);
            $table->string('platform', 20);
            $table->string('uuid', 64);
            $table->string('username', 80);
            $table->string('server_name', 100);
            $table->string('world', 100);
            $table->decimal('x', 12, 3);
            $table->decimal('y', 12, 3);
            $table->decimal('z', 12, 3);
            $table->decimal('yaw', 8, 3)->nullable();
            $table->decimal('pitch', 8, 3)->nullable();
            $table->text('raw_message');
            $table->text('filtered_message')->nullable();
            $table->boolean('passed');
            $table->string('failure_reason', 40)->nullable();
            $table->text('failure_detail')->nullable();
            $table->json('context')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('admin_failure_notification_queued_at')->nullable();
            $table->timestamps();

            $table->index(['occurred_at', 'id']);
            $table->index(['minecraft_account_id', 'occurred_at']);
            $table->index(['uuid', 'occurred_at']);
            $table->index(['message_type', 'occurred_at']);
            $table->index(['passed', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_messages');
    }
};
