<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    public function up(): void
    {
        Schema::create('tax_adjustment_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tax_adjustment_id')->constrained('tax_adjustments')->cascadeOnDelete();
            $table->foreignId('invoice_line_id')->nullable()->constrained('invoice_lines')->nullOnDelete();
            $table->unsignedInteger('line_number');
            $table->string('description', 255);
            $table->text('notes')->nullable();
            $table->decimal('quantity', 10, 2)->default(0);
            $table->decimal('unit_price_ex_tax', 10, 2)->default(0);
            $table->decimal('tax_rate', 6, 4)->default(0.1000);
            $table->decimal('line_total_ex_tax', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('line_total_inc_tax', 10, 2)->default(0);
            $table->timestamps();

            $table->unique(['tax_adjustment_id', 'line_number']);
            $table->index('invoice_line_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tax_adjustment_lines');
    }
};

