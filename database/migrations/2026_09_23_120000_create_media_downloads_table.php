<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_downloads', function (Blueprint $table): void {
            $table->id();
            $table->string('media_name')->index();
            $table->string('user_id')->nullable()->index();
            $table->string('variant', 40)->nullable();
            $table->string('source', 40)->default('download')->index();
            $table->timestamps();

            $table->index(['media_name', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_downloads');
    }
};
