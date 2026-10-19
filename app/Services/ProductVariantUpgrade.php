<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class ProductVariantUpgrade
{
    /** Keep the old, implicit option identifiable after a different option becomes the default. */
    public function convert(Product $product, bool $migrateReferences = true): ?ProductVariant
    {
        if (! $product->isSellable()) {
            return null;
        }

        return DB::transaction(function () use ($product, $migrateReferences): ProductVariant {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            if ($locked->legacy_base_variant_id) {
                $product->setRawAttributes($locked->getAttributes(), true);

                return $locked->variants()->findOrFail($locked->legacy_base_variant_id);
            }

            $fields = [
                'sku', 'price', 'inventory_units', 'weight_grams',
                'length_mm', 'width_mm', 'height_mm', 'low_stock_threshold', 'low_stock_alert_sent_at',
                'is_preorder', 'preorder_shipping_estimate', 'allow_backorder',
                'backorder_shipping_estimate', 'backorder_shipping_estimate_type',
                'backorder_shipping_offset_days', 'automatic_pricing', 'restocked_at',
            ];
            $variant = $locked->variants()->create([
                ...$locked->only($fields),
                'name' => $locked->baseOptionName(),
                'description' => $locked->baseOptionDescription(),
                'inventory_units' => max(1, (int) $locked->inventory_units),
                'inventory_quantity' => $locked->shared_inventory ? null : $locked->inventory_quantity,
                'bulk_discount_percent' => 0,
                'is_active' => true,
                'sort_order' => 0,
            ]);
            $locked->forceFill([
                'legacy_base_variant_id' => $variant->id,
                'default_variant_id' => $variant->id,
                'inventory_quantity' => $locked->shared_inventory ? $locked->inventory_quantity : null,
            ])->save();

            if ($migrateReferences) {
                $this->rewriteReferences($locked, $variant);
            }

            $product->setRawAttributes($locked->getAttributes(), true);
            $product->unsetRelation('variants');

            return $variant;
        });
    }

    public function restore(Product $product): void
    {
        DB::transaction(function () use ($product): void {
            $locked = Product::query()->lockForUpdate()->findOrFail($product->id);
            $original = $locked->originalVariant();
            if (! $original) {
                return;
            }
            $locked->forceFill([
                ...$original->only(['sku', 'price', 'inventory_units', 'weight_grams', 'length_mm', 'width_mm', 'height_mm', 'low_stock_threshold', 'low_stock_alert_sent_at', 'is_preorder', 'preorder_shipping_estimate', 'allow_backorder', 'backorder_shipping_estimate', 'backorder_shipping_estimate_type', 'backorder_shipping_offset_days', 'automatic_pricing']),
                'price' => $original->effectivePrice(),
                'base_variant_name' => $original->name,
                'base_variant_description' => $original->description,
                'inventory_quantity' => $locked->shared_inventory ? $locked->inventory_quantity : $original->inventory_quantity,
                'legacy_base_variant_id' => null, 'default_variant_id' => null,
            ])->save();
            $this->rewriteReferences($locked, $original, reverse: true);
            DB::table('product_purchases')->where('product_variant_id', $original->id)->update(['product_variant_id' => null]);
            $allocation = DB::table('finance_product_allocations')->where('product_id', $locked->id)->where('variant_id', $original->id)->first();
            if ($allocation) {
                DB::table('finance_product_allocations')->where('product_id', $locked->id)->whereNull('variant_id')->delete();
                DB::table('finance_product_allocations')->where('scope', $allocation->scope)->update(['variant_id' => null, 'scope' => 'product:'.$locked->id.':0']);
            }
            $original->delete();
            $product->setRawAttributes($locked->getAttributes(), true);
            $product->unsetRelation('variants');
        });
    }

    private function rewriteReferences(Product $product, ProductVariant $variant, bool $reverse = false): void
    {
        $from = $reverse ? $variant->id : null;
        $to = $reverse ? null : $variant->id;
        foreach ([['store_order_items', 'product_id', 'product_variant_id'], ['product_recipe_components', 'component_product_id', 'component_variant_id'], ['pick_list_template_items', 'product_id', 'product_variant_id']] as [$table, $productKey, $variantKey]) {
            DB::table($table)->where($productKey, $product->id)->where($variantKey, $from)->update([$variantKey => $to]);
        }
        DB::table('invoice_lines')->where('source_type', Product::class)->where('source_id', $product->id)
            ->orderBy('id')->chunkById(200, function ($lines) use ($from, $to): void {
                foreach ($lines as $line) {
                    $details = json_decode($line->details_json ?? '{}', true) ?: [];
                    $selected = ($details['variant_id'] ?? null) ?: ($details['store_context']['variant_id'] ?? null);
                    if ((int) $selected === (int) $from) {
                        $details['variant_id'] = $to;
                        if (isset($details['store_context'])) {
                            $details['store_context']['variant_id'] = $to;
                        }
                        DB::table('invoice_lines')->where('id', $line->id)->update(['details_json' => json_encode($details)]);
                    }
                }
            });
        DB::table('quotes')->orderBy('id')->chunkById(200, function ($quotes) use ($product, $from, $to): void {
            foreach ($quotes as $quote) {
                $items = json_decode($quote->line_items ?? '[]', true) ?: [];
                $changed = false;
                foreach ($items as &$item) {
                    if (! is_array($item) || ($item['kind'] ?? '') !== 'product' || (int) ($item['source_id'] ?? 0) !== $product->id) {
                        continue;
                    }
                    $selected = ($item['source_variant_id'] ?? null) ?: data_get($item, 'details_json.variant_id') ?: data_get($item, 'details_json.store_context.variant_id') ?: data_get($item, 'store_context.variant_id');
                    if ((int) $selected === (int) $from) {
                        $item['source_variant_id'] = $to;
                        $item['details_json']['variant_id'] = $to;
                        if (isset($item['details_json']['store_context'])) {
                            $item['details_json']['store_context']['variant_id'] = $to;
                        }
                        if (isset($item['store_context'])) {
                            $item['store_context']['variant_id'] = $to;
                        }
                        $changed = true;
                    }
                }
                unset($item);
                if ($changed) {
                    DB::table('quotes')->where('id', $quote->id)->update(['line_items' => json_encode($items)]);
                }
            }
        });
        DB::table('invoices')->whereNotNull('inventory_reservations')->orderBy('id')
            ->chunkById(200, function ($invoices) use ($product, $variant, $reverse): void {
                foreach ($invoices as $invoice) {
                    $reservations = json_decode($invoice->inventory_reservations, true) ?: [];
                    $oldKey = $product->id.':'.($reverse ? $variant->id : 0);
                    if (! isset($reservations[$oldKey])) {
                        continue;
                    }
                    $reservation = $reservations[$oldKey];
                    unset($reservations[$oldKey]);
                    if (! $product->shared_inventory && $reservation['source'] === ($reverse ? 'variant' : 'product')) {
                        $reservation['source'] = $reverse ? 'product' : 'variant';
                        $reservation['id'] = $reverse ? $product->id : $variant->id;
                    }
                    $reservations[$product->id.':'.($reverse ? 0 : $variant->id)] = $reservation;
                    DB::table('invoices')->where('id', $invoice->id)->update(['inventory_reservations' => json_encode($reservations)]);
                }
            });
        DB::table('workshops')->where('pick_list_is_customized', true)->orderBy('id')
            ->chunkById(200, function ($workshops) use ($product, $from, $to): void {
                foreach ($workshops as $workshop) {
                    $items = json_decode($workshop->pick_list_custom_items ?? '[]', true) ?: [];
                    $changed = false;
                    foreach ($items as &$item) {
                        if (is_array($item) && (int) ($item['product_id'] ?? 0) === $product->id && (int) ($item['product_variant_id'] ?? 0) === (int) $from) {
                            $item['product_variant_id'] = $to;
                            $changed = true;
                        }
                    }
                    unset($item);
                    if ($changed) {
                        DB::table('workshops')->where('id', $workshop->id)->update(['pick_list_custom_items' => json_encode($items)]);
                    }
                }
            });
    }
}
