<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('workshop_category_workshop');
        Schema::dropIfExists('workshop_categories');

        Schema::create('workshop_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('icon_class', 120)->nullable();
            $table->boolean('hide_in_footer')->default(false);
            $table->timestamps();
        });

        Schema::create('workshop_category_workshop', function (Blueprint $table): void {
            $table->string('workshop_id');
            $table->foreignId('workshop_category_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['workshop_id', 'workshop_category_id']);
            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workshop_category_workshop');
        Schema::dropIfExists('workshop_categories');
    }
};
