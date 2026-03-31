<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_post_attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('forum_post_id')->constrained('forum_posts')->cascadeOnDelete();
            $table->foreignUuid('uploaded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('storage_path');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['forum_post_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_post_attachments');
    }
};
