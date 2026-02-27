<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_files', function (Blueprint $table): void {
            $table->id();
            $table->string('path')->unique();
            $table->string('original_name');
            $table->string('mime_type', 191)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->string('user_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('finance_fileables', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('finance_file_id');
            $table->string('fileable_id');
            $table->string('fileable_type');
            $table->string('collection')->nullable();
            $table->timestamps();

            $table->index(['fileable_id', 'fileable_type'], 'finance_fileables_fileable_idx');
            $table->unique(['finance_file_id', 'fileable_id', 'fileable_type', 'collection'], 'finance_fileables_unique_idx');
            $table->foreign('finance_file_id')->references('id')->on('finance_files')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_fileables');
        Schema::dropIfExists('finance_files');
    }
};

