<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_help_requests', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('type', ['screen', 'camera']);
            $table->enum('status', ['pending', 'approved', 'done', 'rejected'])->default('pending');
            $table->foreignUuid('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['class_session_id', 'status', 'created_at']);
            $table->index(['class_session_id', 'user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_help_requests');
    }
};
