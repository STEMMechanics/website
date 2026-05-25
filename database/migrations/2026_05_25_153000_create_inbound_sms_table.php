<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inbound_sms', function (Blueprint $table): void {
            $table->id();
            $table->string('provider')->default('smsflow')->index();
            $table->string('topic')->index();
            $table->string('incoming_id')->unique();
            $table->string('original_message_id')->nullable()->index();
            $table->unsignedBigInteger('sent_sms_id')->nullable()->index();
            $table->timestamp('acknowledged_at')->nullable()->index();
            $table->uuid('acknowledged_by_user_id')->nullable()->index();
            $table->string('originator');
            $table->string('destination')->nullable();
            $table->longText('message');
            $table->timestamp('received_at')->nullable();
            $table->boolean('opted_out')->default(false);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->foreign('sent_sms_id')
                ->references('id')
                ->on('sent_sms')
                ->nullOnDelete();

            $table->foreign('acknowledged_by_user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->index(['provider', 'topic', 'received_at']);
            $table->index(['originator', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inbound_sms');
    }
};
