<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_attendances', function (Blueprint $table): void {
            $table->id();
            $table->string('workshop_id')->index();
            $table->unsignedBigInteger('ticket_id')->nullable()->index();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 30)->default('dropin');
            $table->string('firstname')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->timestamp('attended_at')->nullable();
            $table->timestamps();

            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
            $table->foreign('ticket_id')->references('id')->on('tickets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_attendances');
    }
};
