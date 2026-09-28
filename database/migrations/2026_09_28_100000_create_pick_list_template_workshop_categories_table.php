<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_list_template_workshop_category', function (Blueprint $table): void {
            $table->foreignId('pick_list_template_id')->constrained('pick_list_templates')->cascadeOnDelete();
            $table->foreignId('workshop_category_id')->constrained('workshop_categories')->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['pick_list_template_id', 'workshop_category_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_template_workshop_category');
    }
};
