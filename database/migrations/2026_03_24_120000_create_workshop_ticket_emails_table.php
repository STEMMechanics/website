<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_ticket_emails', function (Blueprint $table): void {
            $table->id();
            $table->string('workshop_id')->index();
            $table->json('ticket_ids');
            $table->unsignedBigInteger('invoice_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->string('recipient_email')->nullable();
            $table->string('recipient_name')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('amount', 10, 2)->default(0);
            $table->string('status')->default('pending')->index();
            $table->timestamp('queued_at')->nullable()->index();
            $table->timestamp('failed_at')->nullable()->index();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
            $table->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
            $table->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_ticket_emails');
    }
};
