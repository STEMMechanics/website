<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pick_list_template_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pick_list_template_id')->constrained()->cascadeOnDelete();
            $table->string('item_name');
            $table->string('quantity_type', 32)->default('per_participant');
            $table->unsignedInteger('quantity_value')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['pick_list_template_id', 'sort_order'], 'plt_items_template_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_template_items');
    }
};
