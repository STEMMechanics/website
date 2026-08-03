<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->string('perceptual_hash', 16)->nullable()->after('hash')->index();
            $table->timestamp('perceptual_hash_scanned_at')->nullable()->after('perceptual_hash');
        });

        Schema::create('media_similarity_ignores', function (Blueprint $table): void {
            $table->id();
            $table->string('media_name_a');
            $table->string('media_name_b');
            $table->timestamps();
            $table->unique(['media_name_a', 'media_name_b'], 'media_similarity_ignores_pair_unique');
            $table->foreign('media_name_a')->references('name')->on('media')->cascadeOnDelete();
            $table->foreign('media_name_b')->references('name')->on('media')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_similarity_ignores');

        Schema::table('media', function (Blueprint $table): void {
            $table->dropIndex(['perceptual_hash']);
            $table->dropColumn(['perceptual_hash', 'perceptual_hash_scanned_at']);
        });
    }
};
