<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_items')) {
            Schema::create('stock_items', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('sku')->nullable()->unique();
            $table->string('image_media_name')->nullable();
            $table->string('unit', 32)->default('each');
            $table->string('status', 20)->default('active')->index();
            $table->decimal('on_hand_quantity', 12, 3)->default(0);
            $table->decimal('reorder_point', 12, 3)->default(0);
            $table->decimal('replacement_unit_cost_ex_tax', 12, 4)->nullable();
            $table->char('replacement_cost_currency', 3)->default('AUD');
            $table->string('replacement_cost_source', 40)->nullable();
            $table->timestamp('replacement_cost_updated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('image_media_name')->references('name')->on('media')->nullOnDelete();
            });
        }

        if (! Schema::hasTable('stock_receipts')) {
            Schema::create('stock_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->nullable()->constrained('finance_supplier_rules')->nullOnDelete();
            $table->foreignId('expense_id')->nullable()->constrained('expenses')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reference')->nullable();
            $table->dateTime('received_at');
            $table->char('currency', 3)->default('AUD');
            $table->decimal('exchange_rate', 12, 6)->default(1);
            $table->decimal('freight_ex_tax', 12, 4)->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['supplier_id', 'received_at']);
            });
        }

        if (! Schema::hasTable('stock_receipt_lines')) {
            Schema::create('stock_receipt_lines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_receipt_id')->constrained('stock_receipts')->cascadeOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost_ex_tax', 12, 4);
            $table->decimal('total_cost_ex_tax', 12, 4);
            $table->timestamps();

            $table->index(['stock_item_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('stock_movements')) {
            Schema::create('stock_movements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->foreignId('stock_receipt_line_id')->nullable()->constrained('stock_receipt_lines')->nullOnDelete();
            $table->string('movement_type', 32)->index();
            $table->decimal('quantity', 12, 3);
            $table->decimal('unit_cost_ex_tax', 12, 4)->nullable();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('occurred_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['stock_item_id', 'occurred_at']);
            });
        }

        if (! Schema::hasTable('stock_reservations')) {
            Schema::create('stock_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_item_id')->constrained('stock_items')->cascadeOnDelete();
            $table->string('source_type')->nullable();
            $table->string('source_id')->nullable();
            $table->string('purpose', 32);
            $table->string('status', 24)->default('active')->index();
            $table->decimal('quantity', 12, 3);
            $table->decimal('remaining_quantity', 12, 3);
            $table->decimal('unit_cost_snapshot', 12, 4)->nullable();
            $table->json('metadata')->nullable();
            $table->dateTime('reserved_at');
            $table->dateTime('released_at')->nullable();
            $table->dateTime('consumed_at')->nullable();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['source_type', 'source_id']);
            $table->index(['stock_item_id', 'status']);
            });
        }

        $productStockItemMissing = ! Schema::hasColumn('products', 'stock_item_id');
        $productQuantityMissing = ! Schema::hasColumn('products', 'stock_quantity_per_sale');
        if ($productStockItemMissing || $productQuantityMissing) {
            Schema::table('products', function (Blueprint $table) use ($productStockItemMissing, $productQuantityMissing): void {
                if ($productStockItemMissing) {
                    $table->foreignId('stock_item_id')->nullable()->after('inventory_quantity')->constrained('stock_items')->nullOnDelete();
                }
                if ($productQuantityMissing) {
                    $table->decimal('stock_quantity_per_sale', 12, 3)->nullable()->after('stock_item_id');
                }
            });
        }

        $variantStockItemMissing = ! Schema::hasColumn('product_variants', 'stock_item_id');
        $variantQuantityMissing = ! Schema::hasColumn('product_variants', 'stock_quantity_per_sale');
        if ($variantStockItemMissing || $variantQuantityMissing) {
            Schema::table('product_variants', function (Blueprint $table) use ($variantStockItemMissing, $variantQuantityMissing): void {
                if ($variantStockItemMissing) {
                    $table->foreignId('stock_item_id')->nullable()->after('inventory_quantity')->constrained('stock_items')->nullOnDelete();
                }
                if ($variantQuantityMissing) {
                    $table->decimal('stock_quantity_per_sale', 12, 3)->nullable()->after('stock_item_id');
                }
            });
        }

        if (! Schema::hasTable('product_stock_components')) {
            Schema::create('product_stock_components', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();
            $table->foreignId('stock_item_id')->constrained('stock_items')->restrictOnDelete();
            $table->decimal('quantity', 12, 3)->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'product_variant_id', 'sort_order'], 'product_stock_components_product_variant_sort_idx');
            $table->index(['stock_item_id', 'product_id']);
            });
        }

        $pickListStockItemMissing = ! Schema::hasColumn('pick_list_template_items', 'stock_item_id');
        $pickListQuantityMissing = ! Schema::hasColumn('pick_list_template_items', 'stock_quantity');
        if ($pickListStockItemMissing || $pickListQuantityMissing) {
            Schema::table('pick_list_template_items', function (Blueprint $table) use ($pickListStockItemMissing, $pickListQuantityMissing): void {
                if ($pickListStockItemMissing) {
                    $table->foreignId('stock_item_id')->nullable()->after('item_name')->constrained('stock_items')->nullOnDelete();
                }
                if ($pickListQuantityMissing) {
                    $table->decimal('stock_quantity', 12, 3)->nullable()->after('quantity_value');
                }
            });
        }

    }

    public function down(): void
    {
        if (Schema::hasColumn('workshops', 'stock_cost_threshold_ex_tax')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->dropColumn('stock_cost_threshold_ex_tax');
            });
        }
        if (Schema::hasColumn('pick_list_templates', 'stock_cost_threshold_ex_tax')) {
            Schema::table('pick_list_templates', function (Blueprint $table): void {
                $table->dropColumn('stock_cost_threshold_ex_tax');
            });
        }
        Schema::table('pick_list_template_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_item_id');
            $table->dropColumn('stock_quantity');
        });
        Schema::dropIfExists('product_stock_components');
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_item_id');
            $table->dropColumn('stock_quantity_per_sale');
        });
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('stock_item_id');
            $table->dropColumn('stock_quantity_per_sale');
        });
        Schema::dropIfExists('stock_reservations');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_receipt_lines');
        Schema::dropIfExists('stock_receipts');
        Schema::dropIfExists('stock_items');
    }
};
