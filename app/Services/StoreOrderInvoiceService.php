<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use App\Services\Finance\InvoiceInventory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StoreOrderInvoiceService
{
    public function __construct(
        private readonly StoreOrderService $orders,
        private readonly InvoiceInventory $invoiceInventory,
        private readonly StockInventoryService $stockInventory,
        private readonly StoreOrderUpdateService $updates,
    ) {}

    /** @return Builder<Invoice> */
    public function availableInvoices(StoreOrder $order): Builder
    {
        $query = Invoice::query()
            ->whereIn('status', [Invoice::STATUS_ISSUED, Invoice::STATUS_SENT, Invoice::STATUS_PAID, Invoice::STATUS_OVERDUE])
            ->where('total_amount', '>=', 0)
            ->whereDoesntHave('storeOrders')
            ->whereNotIn('id', StoreOrder::query()->whereNotNull('invoice_id')->select('invoice_id'))
            ->whereDoesntHave('tickets')
            ->whereDoesntHave('taxAdjustments')
            ->whereDoesntHave('lines', fn ($query) => $query->where('kind', '!=', 'discount')->where('line_total_inc_tax', '<', 0))
            ->whereDoesntHave('lines', fn ($query) => $query->where('kind', 'discount')->where('line_total_inc_tax', '>', 0))
            ->whereDoesntHave('lines', fn ($query) => $query->whereNotIn('kind', ['product', 'generic', 'shipping', 'discount']))
            ->whereHas('lines', fn ($query) => $query->whereIn('kind', ['product', 'generic'])->where('quantity', '>', 0));

        $order->loadMissing('user.primaryOrganisation');
        $emails = collect([$order->billing_email, $order->user?->email])
            ->map(fn ($email): string => mb_strtolower(trim((string) $email)))->filter()->unique()->values()->all();
        $company = mb_strtolower(trim((string) ($order->billing_company ?: $order->user?->primaryOrganisation?->name)));
        if (! $order->user_id && $emails === [] && $company === '') {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function ($query) use ($order, $emails, $company): void {
            $query->whereRaw('1 = 0');
            if ($order->user_id) {
                $query->orWhere('user_id', $order->user_id);
            }
            if ($emails !== []) {
                $placeholders = implode(', ', array_fill(0, count($emails), '?'));
                $query->orWhereRaw('LOWER(TRIM(billing_email)) IN ('.$placeholders.')', $emails)
                    ->orWhereHas('user', fn ($userQuery) => $userQuery->whereRaw('LOWER(TRIM(email)) IN ('.$placeholders.')', $emails));
            }
            if ($company !== '') {
                $query->orWhereRaw('LOWER(TRIM(billing_company)) = ?', [$company])
                    ->orWhere(function ($companyQuery) use ($company): void {
                        $companyQuery->where(fn ($query) => $query->whereNull('billing_company')->orWhereRaw("TRIM(billing_company) = ''"))
                            ->whereHas('user.primaryOrganisation', fn ($organisationQuery) => $organisationQuery->whereRaw('LOWER(TRIM(name)) = ?', [$company]));
                    });
            }
        });
    }

    /** @return Builder<Invoice> */
    public function availableStoreInvoices(StoreOrder $order): Builder
    {
        return $this->availableInvoices($order)->whereHas('lines', function ($query): void {
            $query->whereIn('kind', ['product', 'generic'])->where('quantity', '>', 0)
                ->where(function ($productQuery): void {
                    $productQuery->where(fn ($query) => $query->where('source_type', Product::class)->where('source_id', '>', 0))
                        ->orWhere('details_json->store_context->product_id', '>', 0);
                });
        });
    }

    /** Prices, payments and invoice descriptions are preserved; mappings only control fulfilment. */
    public function attach(StoreOrder $order, int $invoiceId, array $mappings, string $shipmentPlan, ?User $actor): StoreOrder
    {
        return DB::transaction(function () use ($order, $invoiceId, $mappings, $shipmentPlan, $actor): StoreOrder {
            $order = StoreOrder::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            if (! $order->canAttachInvoice()) {
                throw ValidationException::withMessages(['invoice_id' => 'Additional invoices can only be attached to an open order.']);
            }

            $invoice = $this->availableInvoices($order)->whereKey($invoiceId)->lockForUpdate()->first();
            if (! $invoice instanceof Invoice) {
                throw ValidationException::withMessages(['invoice_id' => 'Choose an issued invoice for this customer or company that is not already linked to an order or credited.']);
            }
            $invoice->load(['lines', 'allocations.customerPayment']);

            $lines = $invoice->lines->keyBy('id');
            $selected = collect($mappings)->filter(fn (array $row): bool => ! empty($row['product_id']));
            if ($selected->isEmpty()) {
                throw ValidationException::withMessages(['items' => 'Match at least one invoice line to a product to add to the order.']);
            }
            if ($selected->pluck('invoice_line_id')->unique()->count() !== $selected->count()) {
                throw ValidationException::withMessages(['items' => 'Each invoice line can only be added once.']);
            }

            $prepared = [];
            foreach ($selected as $index => $row) {
                $line = $lines->get((int) $row['invoice_line_id']);
                if (! $line instanceof InvoiceLine || ! in_array($line->kind, ['product', 'generic'], true)
                    || (float) $line->quantity <= 0 || floor((float) $line->quantity) !== (float) $line->quantity
                    || (float) $line->line_total_inc_tax < 0) {
                    throw ValidationException::withMessages(['items.'.$index.'.selection' => 'Only positive, whole-quantity product or description lines can become order items.']);
                }
                if (StoreOrderItem::query()->where('invoice_line_id', $line->id)->exists()) {
                    throw ValidationException::withMessages(['items' => 'An invoice line is already part of another order.']);
                }

                $product = Product::query()->sellable()->whereKey((int) $row['product_id'])->lockForUpdate()->first();
                $variantId = (int) ($row['variant_id'] ?? 0);
                $variant = $variantId && $product ? $product->variants()->whereKey($variantId)->lockForUpdate()->first() : null;
                if (! $product || ($variantId && ! $variant)) {
                    throw ValidationException::withMessages(['items.'.$index.'.selection' => 'Choose a valid product and one of its variants.']);
                }

                $invoicedProductId = $this->productId($line);
                $invoicedVariantId = $this->variantId($line);
                if ($invoicedProductId && ($invoicedProductId !== (int) $product->id || $invoicedVariantId !== $variantId)) {
                    throw ValidationException::withMessages(['items.'.$index.'.selection' => 'Use the product and variant already recorded on this invoice line.']);
                }
                if (! $invoicedProductId && (! $product->isActive() || ($variant && ! $variant->is_active))) {
                    throw ValidationException::withMessages(['items.'.$index.'.selection' => 'Choose an active product and variant.']);
                }
                if ($product->isPhysical() && ! $order->contains_physical && ! $order->usesPickup()) {
                    throw ValidationException::withMessages(['items' => 'Physical items need an order with delivery or pickup details.']);
                }
                $prepared[] = [$line, $product, $variant];
            }

            foreach ($invoice->lines->where('kind', 'product') as $line) {
                if (! $selected->contains(fn ($row): bool => (int) $row['invoice_line_id'] === (int) $line->id)) {
                    throw ValidationException::withMessages(['items' => 'Match every product line on the invoice before attaching it.']);
                }
            }
            if (! in_array($shipmentPlan, ['same_shipment', 'separate_shipment'], true) || ($order->usesPickup() && $shipmentPlan !== 'same_shipment')) {
                throw ValidationException::withMessages(['shipment_plan' => 'Choose a valid shipping plan for this order.']);
            }

            // Release standalone reservations before creating order reservations, in the same transaction.
            // A failed mapping or stock check restores both sets of records automatically.
            $this->invoiceInventory->sync($invoice, true);
            $this->stockInventory->syncInvoiceReservations($invoice, true);

            foreach ($prepared as [$line, $product, $variant]) {
                $this->orders->appendInvoicedItem($order, $line, $product, $variant);
            }
            $order->invoices()->syncWithoutDetaching([
                $order->invoice_id => [],
                $invoice->id => ['attached_by' => $actor?->id],
            ]);

            $shipping = round($invoice->lines->where('kind', 'shipping')->sum('line_total_inc_tax'), 2);
            $discount = abs(round($invoice->lines->where('kind', 'discount')->sum('line_total_inc_tax'), 2));
            $order->subtotal_amount = round((float) $order->subtotal_amount + (float) $invoice->total_amount - $shipping + $discount, 2);
            $order->shipping_amount = round((float) $order->shipping_amount + $shipping, 2);
            $order->discount_amount = round((float) $order->discount_amount + $discount, 2);
            $order->gst_amount = round((float) $order->gst_amount + (float) $invoice->gst_amount, 2);
            $order->total_amount = round((float) $order->total_amount + (float) $invoice->total_amount, 2);
            $order->contains_physical = $order->items()->whereIn('product_type', Product::PHYSICAL_TYPES)->exists();
            $order->contains_digital = $order->items()->where('product_type', Product::PRODUCT_TYPE_DIGITAL)->exists();
            $order->contains_preorder = $order->items()->where('is_preorder', true)->exists();
            if ($shipmentPlan === 'separate_shipment') {
                $order->shipment_count = max(1, (int) $order->shipment_count) + 1;
                $order->split_shipments = true;
                $order->consolidate_shipments = false;
            }
            $order->save();

            $this->updates->recordInvoiceAttached($order, $invoice, count($prepared), $shipmentPlan, $actor);
            $order = $order->fresh(['invoices.allocations.customerPayment', 'items']);
            $this->orders->syncOrderState($order);

            return $order;
        });
    }

    public function productId(InvoiceLine $line): int
    {
        return $line->source_type === Product::class
            ? (int) $line->source_id
            : (int) data_get($line->details_json, 'store_context.product_id', 0);
    }

    public function variantId(InvoiceLine $line): int
    {
        return (int) (data_get($line->details_json, 'variant_id') ?: data_get($line->details_json, 'store_context.variant_id', 0));
    }
}
