<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableName = 'pick_list_template_workshop_category';

        if (! Schema::hasTable($tableName)) {
            Schema::create($tableName, function (Blueprint $table): void {
                $table->unsignedBigInteger('pick_list_template_id');
                $table->unsignedBigInteger('workshop_category_id');
                $table->timestamps();

                $table->primary(['pick_list_template_id', 'workshop_category_id']);
            });
        }

        Schema::table($tableName, function (Blueprint $table): void {
            $table->foreign('pick_list_template_id', 'pl_tpl_wkshp_cat_template_fk')
                ->references('id')
                ->on('pick_list_templates')
                ->cascadeOnDelete();
            $table->foreign('workshop_category_id', 'pl_tpl_wkshp_cat_category_fk')
                ->references('id')
                ->on('workshop_categories')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pick_list_template_workshop_category');
    }
};
