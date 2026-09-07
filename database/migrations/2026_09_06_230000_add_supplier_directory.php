<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finance_supplier_rules', function (Blueprint $table) {
            $table->string('name')->nullable();
            $table->foreignId('category_id')->nullable()->constrained('finance_categories')->restrictOnDelete();
        });
        Schema::table('expenses', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->constrained('finance_supplier_rules')->restrictOnDelete();
        });
        DB::table('finance_supplier_rules')->orderBy('id')->each(function ($supplier): void {
            $splits = array_filter(json_decode($supplier->splits, true, 512, JSON_THROW_ON_ERROR), fn ($amount) => (float) $amount > 0);
            $category = count($splits) === 1 && (float) reset($splits) === 100.0 ? array_key_first($splits) : null;
            DB::table('finance_supplier_rules')->where('id', $supplier->id)->update(['name' => $supplier->supplier, 'category_id' => $category]);
        });
        DB::table('expenses')->whereNotNull('supplier')->orderBy('id')->each(function ($expense): void {
            $name = trim($expense->supplier);
            if ($name === '') {
                return;
            }
            $key = mb_strtolower($name);
            $id = DB::table('finance_supplier_rules')->where('supplier', $key)->value('id');
            if ($id === null) {
                $id = DB::table('finance_supplier_rules')->insertGetId(['supplier' => $key, 'name' => $name, 'mode' => 'default', 'splits' => '{}', 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('expenses')->where('id', $expense->id)->update(['supplier_id' => $id]);
        });
    }

    public function down(): void
    {
        Schema::table('expenses', fn (Blueprint $table) => $table->dropConstrainedForeignId('supplier_id'));
        Schema::table('finance_supplier_rules', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('name');
        });
    }
};
