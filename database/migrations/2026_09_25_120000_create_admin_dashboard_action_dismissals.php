<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_dashboard_action_dismissals', function (Blueprint $table): void {
            $table->id();
            $table->foreignUuid('user_id')->constrained()->cascadeOnDelete();
            $table->string('action_key', 100);
            $table->timestamp('dismissed_at');
            $table->timestamps();
            $table->unique(['user_id', 'action_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_dashboard_action_dismissals');
    }
};
