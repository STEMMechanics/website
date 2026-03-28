<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_enrolments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('class_session_id')->constrained('class_sessions')->cascadeOnDelete();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['teacher', 'student'])->default('student');
            $table->timestamps();

            $table->unique(['class_session_id', 'user_id']);
            $table->index(['class_session_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_enrolments');
    }
};
