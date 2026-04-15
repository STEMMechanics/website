<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class() extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('coupons', 'applies_to')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $table->string('applies_to', 20)->default('both')->after('discount_type');
            });
        }

        if (Schema::hasColumn('coupons', 'applies_to')) {
            DB::table('coupons')
                ->whereNull('applies_to')
                ->update(['applies_to' => 'both']);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('coupons', 'applies_to')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $table->dropColumn('applies_to');
            });
        }
    }
};
