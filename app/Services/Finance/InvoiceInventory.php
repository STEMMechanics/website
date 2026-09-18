<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Validation\ValidationException;

class InvoiceInventory
{
    /** Called inside the invoice save transaction, with the invoice locked. */
    public function sync(Invoice $invoice, bool $release = false): void
    {
        // Store orders own their reservations and their fulfilment/refund lifecycle.
        if ($invoice->storeOrders()->exists()) {
            return;
        }

        $previous = $invoice->inventory_reservations ?? [];
        $next = [];
        if (! $release && $invoice->status !== Invoice::STATUS_CANCELLED) {
            foreach ($invoice->lines()->where('kind', 'product')->get() as $line) {
                if ($line->source_type !== Product::class) {
                    continue;
                }
                $product = Product::query()->find($line->source_id);
                if (! $product || ! $product->isPhysical()) {
                    continue;
                }
                $variantId = data_get($line->details_json, 'variant_id') ?: data_get($line->details_json, 'store_context.variant_id');
                $variant = $variantId ? $product->variants()->find($variantId) : null;
                if ($variantId && ! $variant) {
                    throw ValidationException::withMessages(['line_items' => 'The selected product variant is no longer available.']);
                }
                if (! $product->tracksInventory($variant)) {
                    continue;
                }
                $quantity = (float) $line->quantity;
                if ($quantity < 0 || floor($quantity) !== $quantity) {
                    throw ValidationException::withMessages(['line_items' => 'Stock-managed products require a whole, non-negative number of packs.']);
                }
                $selection = $product->id.':'.($variant->id ?? 0);
                // Keep the original pool and pack size when an existing reservation is edited.
                $reservation = $previous[$selection] ?? [
                    'source' => $product->shared_inventory || ! $variant ? 'product' : 'variant',
                    'id' => $product->shared_inventory || ! $variant ? $product->id : $variant->id,
                    'units' => $product->inventoryUnits($variant),
                    'quantity' => 0,
                ];
                $reservation['quantity'] = ($next[$selection]['quantity'] ?? 0) + (int) $quantity;
                $next[$selection] = $reservation;
            }
        }

        $deltas = [];
        foreach ([[$previous, 1], [$next, -1]] as [$reservations, $direction]) {
            foreach ($reservations as $reservation) {
                $key = $reservation['source'].':'.$reservation['id'];
                $deltas[$key] = ($deltas[$key] ?? 0) + $direction * $reservation['units'] * $reservation['quantity'];
            }
        }
        ksort($deltas);
        foreach ($deltas as $key => $delta) {
            if ($delta === 0) {
                continue;
            }
            [$type, $id] = explode(':', $key);
            $model = $type === 'product' ? Product::class : ProductVariant::class;
            $stock = $model::query()->whereKey($id)->lockForUpdate()->first();
            if (! $stock || $stock->inventory_quantity === null) {
                continue;
            }
            if ($stock->inventory_quantity + $delta < 0) {
                throw ValidationException::withMessages(['line_items' => 'Not enough stock remains for '.($stock->title ?? $stock->name).'. Reduce the quantity or update its stock first.']);
            }
            $stock->inventory_quantity += $delta;
            $stock->save();
        }
        $invoice->inventory_reservations = $next;
        $invoice->save();
    }
}
