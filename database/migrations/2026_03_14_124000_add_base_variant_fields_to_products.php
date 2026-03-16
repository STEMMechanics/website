<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('base_variant_name')->nullable()->after('description');
            $table->text('base_variant_description')->nullable()->after('base_variant_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'base_variant_name',
                'base_variant_description',
            ]);
        });
    }
};
