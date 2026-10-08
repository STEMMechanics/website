<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('stock_items', 'is_kit')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->boolean('is_kit')->default(false)->after('status');
            });
        }

        if (! Schema::hasColumn('workshops', 'max_attendance')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->unsignedInteger('max_attendance')->nullable()->after('max_tickets');
            });
        }

        if (! Schema::hasTable('stock_item_components')) {
            Schema::create('stock_item_components', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('kit_stock_item_id')->constrained('stock_items')->cascadeOnDelete();
                $table->foreignId('component_stock_item_id')->constrained('stock_items')->restrictOnDelete();
                $table->decimal('quantity', 12, 3)->default(1);
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['kit_stock_item_id', 'component_stock_item_id'], 'stock_item_components_kit_component_unique');
                $table->index(['component_stock_item_id', 'kit_stock_item_id'], 'stock_item_components_component_kit_idx');
            });
        }

        // MySQL may leave the table behind if an earlier migration attempt
        // fails while creating an automatically named index. Ensure the
        // reverse lookup index exists independently so the migration can be
        // safely rerun after that partial DDL state.
        if (Schema::hasTable('stock_item_components')
            && ! Schema::hasIndex('stock_item_components', 'stock_item_components_component_kit_idx')) {
            Schema::table('stock_item_components', function (Blueprint $table): void {
                $table->index(['component_stock_item_id', 'kit_stock_item_id'], 'stock_item_components_component_kit_idx');
            });
        }

        if (! Schema::hasTable('stock_item_kit_migration_map')) {
            Schema::create('stock_item_kit_migration_map', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('kit_stock_item_id')->unique('stock_kit_migration_kit_unique')->constrained('stock_items')->cascadeOnDelete();
                $table->string('source_key', 120)->unique('stock_kit_migration_source_unique');
                $table->unsignedBigInteger('product_id');
                $table->unsignedBigInteger('product_variant_id')->nullable();
                $table->unsignedBigInteger('previous_stock_item_id')->nullable();
                $table->decimal('previous_stock_quantity_per_sale', 12, 3)->nullable();
                $table->timestamps();
                $table->index(['product_id', 'product_variant_id'], 'stock_kit_migration_product_variant_idx');
            });
        }

        if (Schema::hasTable('product_stock_components')) {
            $legacyRows = DB::table('product_stock_components')
                ->orderBy('product_id')
                ->orderBy('product_variant_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            foreach ($legacyRows->groupBy(fn (object $row): string => $row->product_id.':'.($row->product_variant_id ?? 'base')) as $rows) {
                $first = $rows->first();
                $product = DB::table('products')->where('id', $first->product_id)->first();
                if (! $product) {
                    continue;
                }

                $variant = $first->product_variant_id !== null
                    ? DB::table('product_variants')->where('id', $first->product_variant_id)->first()
                    : null;
                $sourceKey = $first->product_id.':'.($first->product_variant_id ?? 'base');
                $mapping = DB::table('stock_item_kit_migration_map')->where('source_key', $sourceKey)->first();
                $label = trim((string) $product->title.($variant ? ' '.$variant->name : ''));
                $label = $label !== '' ? $label : 'Imported kit';
                $kitId = $mapping ? (int) $mapping->kit_stock_item_id : 0;

                if ($kitId <= 0) {
                    $skuBase = 'kit-'.(Str::slug($label) ?: 'stock-item');
                    $sku = Str::substr($skuBase, 0, 120);
                    $suffix = 2;
                    while (DB::table('stock_items')->whereRaw('LOWER(sku) = ?', [mb_strtolower($sku)])->exists()) {
                        $suffixText = '-'.$suffix++;
                        $sku = Str::substr($skuBase, 0, 120 - strlen($suffixText)).$suffixText;
                    }

                    $kitId = DB::table('stock_items')->insertGetId([
                        'name' => $label,
                        'sku' => $sku,
                        'unit' => 'kit',
                        'status' => 'active',
                        'is_kit' => true,
                        'on_hand_quantity' => 0,
                        'reorder_point' => 0,
                        'replacement_cost_currency' => 'AUD',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $existingStockItemId = $variant ? $variant->stock_item_id : $product->stock_item_id;
                    $existingQuantityPerSale = $variant ? $variant->stock_quantity_per_sale : $product->stock_quantity_per_sale;
                    DB::table('stock_item_kit_migration_map')->insert([
                        'kit_stock_item_id' => $kitId,
                        'source_key' => $sourceKey,
                        'product_id' => $first->product_id,
                        'product_variant_id' => $first->product_variant_id,
                        'previous_stock_item_id' => $existingStockItemId,
                        'previous_stock_quantity_per_sale' => $existingQuantityPerSale,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    if ($variant && $variant->stock_item_id === null) {
                        DB::table('product_variants')->where('id', $variant->id)->update([
                            'stock_item_id' => $kitId,
                            'stock_quantity_per_sale' => 1,
                        ]);
                    } elseif (! $variant && $product->stock_item_id === null) {
                        DB::table('products')->where('id', $product->id)->update([
                            'stock_item_id' => $kitId,
                            'stock_quantity_per_sale' => 1,
                        ]);
                    }
                }

                foreach ($rows as $row) {
                    if ((int) $row->stock_item_id === $kitId) {
                        throw new RuntimeException('A kit cannot contain itself. Review product stock components before applying this migration.');
                    }

                    DB::table('stock_item_components')->updateOrInsert(
                        [
                            'kit_stock_item_id' => $kitId,
                            'component_stock_item_id' => $row->stock_item_id,
                        ],
                        [
                            'quantity' => $row->quantity,
                            'sort_order' => $row->sort_order,
                            'created_at' => $row->created_at ?? now(),
                            'updated_at' => now(),
                        ],
                    );
                }
            }

        }
    }

    public function down(): void
    {
        if (Schema::hasTable('stock_item_components') && Schema::hasTable('stock_item_kit_migration_map')) {
            $unmappedKitHasComponents = DB::table('stock_item_components')
                ->whereNotIn('kit_stock_item_id', DB::table('stock_item_kit_migration_map')->select('kit_stock_item_id'))
                ->exists();
            if ($unmappedKitHasComponents) {
                throw new RuntimeException('Cannot roll back while kits created after this migration still have recipes. Remove or migrate those kits first.');
            }
        }

        $mappings = Schema::hasTable('stock_item_kit_migration_map')
            ? DB::table('stock_item_kit_migration_map')->get()
            : collect();
        foreach ($mappings as $mapping) {
            $kit = DB::table('stock_items')->where('id', $mapping->kit_stock_item_id)->first();
            if (! $kit) {
                continue;
            }
            if ((float) $kit->on_hand_quantity > 0
                || DB::table('stock_movements')->where('stock_item_id', $kit->id)->orWhere('source_id', (string) $kit->id)->exists()
                || DB::table('stock_reservations')->where('stock_item_id', $kit->id)->exists()) {
                throw new RuntimeException('Cannot roll back after an imported kit has stock movements or reservations. Resolve its stock activity first.');
            }

            $product = DB::table('products')->where('id', $mapping->product_id)->first();
            $variant = $mapping->product_variant_id !== null
                ? DB::table('product_variants')->where('id', $mapping->product_variant_id)->first()
                : null;
            $currentStockItemId = $variant ? $variant->stock_item_id : ($product->stock_item_id ?? null);
            if ($currentStockItemId !== null
                && (int) $currentStockItemId !== (int) $mapping->kit_stock_item_id
                && (int) $currentStockItemId !== (int) ($mapping->previous_stock_item_id ?? 0)) {
                throw new RuntimeException('Cannot roll back because a product or variant stock link changed after kit migration.');
            }
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

        if (Schema::hasTable('stock_item_components')) {
            foreach ($mappings as $mapping) {
                $productExists = DB::table('products')->where('id', $mapping->product_id)->exists();
                $variantExists = $mapping->product_variant_id === null
                    || DB::table('product_variants')->where('id', $mapping->product_variant_id)->exists();
                if (! $productExists || ! $variantExists) {
                    continue;
                }

                foreach (DB::table('stock_item_components')
                    ->where('kit_stock_item_id', $mapping->kit_stock_item_id)
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get() as $component) {
                    DB::table('product_stock_components')->insert([
                        'product_id' => $mapping->product_id,
                        'product_variant_id' => $mapping->product_variant_id,
                        'stock_item_id' => $component->component_stock_item_id,
                        'quantity' => $component->quantity,
                        'sort_order' => $component->sort_order,
                        'created_at' => $component->created_at,
                        'updated_at' => $component->updated_at,
                    ]);
                }

                $previousStockItemId = $mapping->previous_stock_item_id !== null
                    && DB::table('stock_items')->where('id', $mapping->previous_stock_item_id)->exists()
                    ? $mapping->previous_stock_item_id
                    : null;
                if ($mapping->product_variant_id !== null) {
                    DB::table('product_variants')->where('id', $mapping->product_variant_id)->update([
                        'stock_item_id' => $previousStockItemId,
                        'stock_quantity_per_sale' => $mapping->previous_stock_quantity_per_sale,
                    ]);
                } else {
                    DB::table('products')->where('id', $mapping->product_id)->update([
                        'stock_item_id' => $previousStockItemId,
                        'stock_quantity_per_sale' => $mapping->previous_stock_quantity_per_sale,
                    ]);
                }

                DB::table('stock_items')->where('id', $mapping->kit_stock_item_id)->update(['status' => 'archived']);
            }

            Schema::drop('stock_item_components');
        }

        if (Schema::hasTable('stock_item_kit_migration_map')) {
            Schema::drop('stock_item_kit_migration_map');
        }

        if (Schema::hasColumn('workshops', 'max_attendance')) {
            Schema::table('workshops', function (Blueprint $table): void {
                $table->dropColumn('max_attendance');
            });
        }
        if (Schema::hasColumn('stock_items', 'is_kit')) {
            Schema::table('stock_items', function (Blueprint $table): void {
                $table->dropColumn('is_kit');
            });
        }
    }
};
