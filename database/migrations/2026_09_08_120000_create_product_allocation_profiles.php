<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_product_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->json('rules');
            $table->timestamps();
        });
        Schema::create('finance_product_allocations', function (Blueprint $table) {
            $table->string('scope')->primary();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('profile_id')->nullable()->constrained('finance_product_profiles')->restrictOnDelete();
            $table->json('rules')->nullable();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->json('product_allocation_snapshot')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('invoice_lines', fn (Blueprint $table) => $table->dropColumn('product_allocation_snapshot'));
        Schema::dropIfExists('finance_product_allocations');
        Schema::dropIfExists('finance_product_profiles');
    }
};
