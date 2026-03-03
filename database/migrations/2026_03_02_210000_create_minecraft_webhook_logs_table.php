<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('minecraft_webhook_logs', function (Blueprint $table): void {
            $table->id();
            $table->string('direction', 20);
            $table->string('status', 20);
            $table->string('event', 120)->nullable();
            $table->string('delivery_id', 120)->nullable()->index();
            $table->string('method', 12)->default('POST');
            $table->text('target_url')->nullable();
            $table->json('request_headers')->nullable();
            $table->json('payload')->nullable();
            $table->longText('raw_body')->nullable();
            $table->unsignedInteger('response_status')->nullable();
            $table->longText('response_body')->nullable();
            $table->text('error_message')->nullable();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('last_attempted_at')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->foreignId('retried_from_id')->nullable()->constrained('minecraft_webhook_logs')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('minecraft_webhook_logs');
    }
};
