<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workshops', function (Blueprint $table) {
            $table->string('format')->default('workshop');
            $table->json('course_sessions')->nullable();
            $table->boolean('welcome_enabled')->default(false);
            $table->string('welcome_subject')->nullable();
            $table->text('welcome_body')->nullable();
            $table->timestamp('welcome_send_at')->nullable()->index();
            $table->unsignedInteger('welcome_generation')->default(1);
        });
        Schema::create('workshop_session_attendance', function (Blueprint $table) {
            $table->id();
            $table->string('workshop_id');
            $table->uuid('session_id');
            $table->foreignId('ticket_id')->constrained('tickets')->cascadeOnDelete();
            $table->timestamp('attended_at');
            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
            $table->unique(['session_id', 'ticket_id']);
        });
        Schema::create('workshop_welcome_deliveries', function (Blueprint $table) {
            $table->id();
            $table->string('workshop_id');
            $table->unsignedInteger('generation');
            $table->string('email');
            $table->string('status')->default('queued');
            $table->timestamp('sent_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
            $table->unique(['workshop_id', 'generation', 'email'], 'workshop_welcome_recipient_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_welcome_deliveries');
        Schema::dropIfExists('workshop_session_attendance');
        Schema::table('workshops', fn (Blueprint $table) => $table->dropColumn([
            'format', 'course_sessions', 'welcome_enabled', 'welcome_subject', 'welcome_body', 'welcome_send_at', 'welcome_generation',
        ]));
    }
};
