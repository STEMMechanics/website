<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_item_groups')) {
            Schema::create('stock_item_groups', function (Blueprint $table): void {
                $table->id();
                $table->string('name')->unique();
                $table->string('kind', 20)->default('items')->index();
                $table->text('shared_outcome')->nullable();
                $table->timestamps();
            });
        }

        $groupColumnMissing = ! Schema::hasColumn('stock_items', 'stock_item_group_id');
        $variantColumnMissing = ! Schema::hasColumn('stock_items', 'variant_name');
        $incrementColumnMissing = ! Schema::hasColumn('stock_items', 'workshop_pick_increment');

        if ($groupColumnMissing || $variantColumnMissing || $incrementColumnMissing) {
            Schema::table('stock_items', function (Blueprint $table) use (
                $groupColumnMissing,
                $variantColumnMissing,
                $incrementColumnMissing
            ): void {
                if ($groupColumnMissing) {
                    $table->foreignId('stock_item_group_id')
                        ->nullable()
                        ->after('image_media_name')
                        ->constrained('stock_item_groups')
                        ->restrictOnDelete();
                }
                if ($variantColumnMissing) {
                    $table->string('variant_name', 100)->nullable()->after('stock_item_group_id');
                }
                if ($incrementColumnMissing) {
                    $table->decimal('workshop_pick_increment', 12, 3)->default(0.001)->after('reorder_point');
                }
            });
        }

        $groupForeignKeyExists = false;
        foreach (Schema::getForeignKeys('stock_items') as $foreignKey) {
            if (in_array('stock_item_group_id', $foreignKey['columns'] ?? [], true)) {
                $groupForeignKeyExists = true;
                break;
            }
        }

        if (! $groupColumnMissing && ! $groupForeignKeyExists) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->foreign('stock_item_group_id')->references('id')->on('stock_item_groups')->restrictOnDelete();
            });
        }

        if (! Schema::hasIndex('stock_items', 'stock_items_group_variant_unique')
            && ! Schema::hasIndex('stock_items', ['stock_item_group_id', 'variant_name'], 'unique')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->unique(['stock_item_group_id', 'variant_name'], 'stock_items_group_variant_unique');
            });
        }
    }

    public function down(): void
    {
        Schema::table('stock_items', function (Blueprint $table): void {
            $table->dropUnique('stock_items_group_variant_unique');
            $table->dropConstrainedForeignId('stock_item_group_id');
            $table->dropColumn(['variant_name', 'workshop_pick_increment']);
        });

        Schema::dropIfExists('stock_item_groups');
    }
};
