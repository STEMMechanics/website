<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sent_sms', function (Blueprint $table): void {
            $table->id();
            $table->string('recipient');
            $table->string('recipient_name')->nullable();
            $table->longText('message');
            $table->string('status')->default('queued');
            $table->string('from_number')->nullable();
            $table->string('origin')->nullable()->index();
            $table->string('reference')->nullable()->index();
            $table->string('provider_message_id')->nullable()->index();
            $table->unsignedInteger('response_status')->nullable();
            $table->json('response_payload')->nullable();
            $table->json('context')->nullable();
            $table->uuid('initiated_by_user_id')->nullable()->index();
            $table->string('initiated_by_name')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamps();

            $table->foreign('initiated_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['status', 'created_at']);
            $table->index(['recipient', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sent_sms');
    }
};
