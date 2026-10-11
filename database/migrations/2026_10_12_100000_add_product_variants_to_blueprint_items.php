<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pick_list_template_items', function (Blueprint $table): void {
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('pick_list_template_items', fn (Blueprint $table) => $table->dropConstrainedForeignId('product_variant_id'));
    }
};
