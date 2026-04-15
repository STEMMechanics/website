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
        if (! Schema::hasColumn('coupons', 'applies_to_products')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $table->boolean('applies_to_products')->default(true)->after('discount_type');
            });
        }

        if (! Schema::hasColumn('coupons', 'applies_to_workshops')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $table->boolean('applies_to_workshops')->default(true)->after('applies_to_products');
            });
        }

        Schema::dropIfExists('coupon_product_restrictions');
        Schema::create('coupon_product_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['coupon_id', 'product_id']);
        });

        Schema::dropIfExists('coupon_workshop_restrictions');
        Schema::create('coupon_workshop_restrictions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('coupon_id')->constrained('coupons')->cascadeOnDelete();
            $table->string('workshop_id');
            $table->timestamps();

            $table->unique(['coupon_id', 'workshop_id']);
            $table->foreign('workshop_id')->references('id')->on('workshops')->cascadeOnDelete();
        });

        if (Schema::hasColumn('coupons', 'applies_to')) {
            DB::table('coupons')->update([
                'applies_to_products' => DB::raw("CASE WHEN applies_to = 'tickets' THEN 0 ELSE 1 END"),
                'applies_to_workshops' => DB::raw("CASE WHEN applies_to = 'products' THEN 0 ELSE 1 END"),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('coupon_workshop_restrictions')) {
            Schema::drop('coupon_workshop_restrictions');
        }

        if (Schema::hasTable('coupon_product_restrictions')) {
            Schema::drop('coupon_product_restrictions');
        }

        if (Schema::hasColumn('coupons', 'applies_to_workshops') || Schema::hasColumn('coupons', 'applies_to_products')) {
            Schema::table('coupons', function (Blueprint $table): void {
                $columns = [];
                if (Schema::hasColumn('coupons', 'applies_to_products')) {
                    $columns[] = 'applies_to_products';
                }
                if (Schema::hasColumn('coupons', 'applies_to_workshops')) {
                    $columns[] = 'applies_to_workshops';
                }

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
