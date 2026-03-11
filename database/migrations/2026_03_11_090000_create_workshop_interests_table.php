<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('workshop_interests', function (Blueprint $table): void {
            $table->id();
            $table->string('workshop_id')->index();
            $table->foreignUuid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->string('email');
            $table->string('phone');
            $table->timestamps();

            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
            $table->unique(['workshop_id', 'user_id']);
            $table->index(['workshop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_interests');
    }
};
