<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_backup_runs', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type', 20)->index();
            $table->string('status', 20)->index();
            $table->unsignedTinyInteger('progress')->default(0);
            $table->string('message')->nullable();
            $table->text('error_message')->nullable();
            $table->json('result')->nullable();
            $table->foreignUuid('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_backup_runs');
    }
};
