<?php

use App\Support\RequestMemo;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_gst_rates', function (Blueprint $table): void {
            $table->id();
            $table->date('effective_from')->unique();
            $table->decimal('rate', 6, 4);
            $table->string('price_basis', 16)->default('exclusive');
            $table->timestamps();
        });
        DB::table('finance_gst_rates')->insert(['effective_from' => '2000-07-01', 'rate' => 0.10, 'created_at' => now(), 'updated_at' => now()]);
        $oldRate = DB::table('site_options')->where('name', 'finance.gst-rate')->value('value');
        if (is_numeric($oldRate) && (float) $oldRate >= 0 && (float) $oldRate <= 1 && abs((float) $oldRate - 0.10) > 0.00001) {
            // Preserve an explicitly configured sponsorship rate from the day configuration is unified.
            DB::table('finance_gst_rates')->insert(['effective_from' => today()->toDateString(), 'rate' => (float) $oldRate, 'created_at' => now(), 'updated_at' => now()]);
        }
        DB::table('site_options')->where('name', 'finance.gst-rate')->delete();
        foreach (['products', 'product_variants', 'workshops'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->date('price_gst_effective_from')->nullable();
                if ($tableName === 'workshops') {
                    $table->date('early_bird_price_gst_effective_from')->nullable();
                }
            });
        }
        app(RequestMemo::class)->clear();
    }

    public function down(): void
    {
        $rate = DB::table('finance_gst_rates')->whereDate('effective_from', '<=', today())->orderByDesc('effective_from')->value('rate') ?? 0.10;
        DB::table('site_options')->updateOrInsert(['name' => 'finance.gst-rate'], ['value' => (string) $rate]);
        foreach (['products', 'product_variants', 'workshops'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropColumn('price_gst_effective_from');
                if ($tableName === 'workshops') {
                    $table->dropColumn('early_bird_price_gst_effective_from');
                }
            });
        }
        Schema::dropIfExists('finance_gst_rates');
        app(RequestMemo::class)->clear();
    }
};
