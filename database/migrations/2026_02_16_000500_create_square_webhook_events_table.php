<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('square_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('event_id', 120)->unique();
            $table->string('event_type', 120)->nullable();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('square_refund_operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->unsignedBigInteger('tax_adjustment_id')->nullable();
            $table->foreignId('ticket_id')->nullable()->constrained('tickets')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('idempotency_key', 120)->unique();
            $table->unsignedBigInteger('requested_cents');
            $table->unsignedBigInteger('refunded_cents')->default(0);
            $table->string('square_refund_id', 120)->nullable();
            $table->enum('status', ['pending', 'completed', 'failed', 'manual_required'])->default('pending')->index();
            $table->text('failure_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index('tax_adjustment_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('square_refund_operations');
        Schema::dropIfExists('square_webhook_events');
    }
};
