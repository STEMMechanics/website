<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('class_sessions', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('room_name')->unique();
            $table->string('access_group_slug')->nullable()->index();
            $table->foreignUuid('forum_category_id')->nullable()->constrained('forum_categories')->nullOnDelete();
            $table->foreignUuid('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('duplicated_from_class_session_id')->nullable()->constrained('class_sessions')->nullOnDelete();
            $table->string('summary')->nullable();
            $table->longText('instructions_html')->nullable();
            $table->boolean('live_chat_enabled')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('class_sessions');
    }
};
