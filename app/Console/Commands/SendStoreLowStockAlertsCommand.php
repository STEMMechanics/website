<?php

namespace App\Console\Commands;

use App\Jobs\SendEmail;
use App\Mail\StoreLowStockAdminAlert;
use App\Models\Product;
use App\Models\StoreOrderItem;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SendStoreLowStockAlertsCommand extends Command
{
    protected $signature = 'store:products:send-low-stock-alerts';

    protected $description = 'Queue admin low-stock warning emails for store products that need replenishment';

    public function handle(): int
    {
        if (! Schema::hasTable('products')) {
            return self::SUCCESS;
        }

        $products = Product::query()
            ->with('variants')
            ->where('status', Product::STATUS_ACTIVE)
            ->where('product_type', Product::PRODUCT_TYPE_PHYSICAL)
            ->get();

        if ($products->isEmpty()) {
            $this->info('No active physical products found.');

            return self::SUCCESS;
        }

        $productIds = $products->pluck('id')->map(fn ($id) => (int) $id)->all();
        $summaries = $this->inventorySummariesByProduct($productIds);
        $alertProducts = [];
        $alertProductIds = [];

        foreach ($products as $product) {
            $available = $product->trackedInventoryTotal();
            $threshold = $product->effectiveLowStockThreshold();
            $summary = $summaries[(int) $product->id] ?? [
                'awaiting' => 0,
                'reserved' => 0,
                'backorder' => 0,
                'preorder' => 0,
            ];

            $shouldAlert = $threshold !== null
                && $available !== null
                && $available <= $threshold;

            if (! $shouldAlert) {
                if ($product->low_stock_alert_sent_at !== null) {
                    $product->low_stock_alert_sent_at = null;
                    $product->save();
                }

                continue;
            }

            if ($product->low_stock_alert_sent_at !== null) {
                continue;
            }

            $alertProducts[] = [
                'title' => (string) $product->title,
                'product_type_label' => Product::productTypeLabel((string) $product->product_type),
                'available' => $available,
                'low_stock_threshold' => $threshold,
                'awaiting' => (int) ($summary['awaiting'] ?? 0),
                'reserved' => (int) ($summary['reserved'] ?? 0),
                'backorder' => (int) ($summary['backorder'] ?? 0),
                'preorder' => (int) ($summary['preorder'] ?? 0),
                'edit_url' => route('admin.shop.product.edit', $product),
                'notes_excerpt' => ($notes = trim((string) ($product->private_notes ?? ''))) !== ''
                    ? Str::limit(str_replace(["\r\n", "\r"], "\n", $notes), 160)
                    : null,
            ];
            $alertProductIds[] = (int) $product->id;
        }

        $recipients = $this->adminRecipients();
        if ($alertProducts === []) {
            $this->info('No new low-stock alerts to send.');

            return self::SUCCESS;
        }

        if ($recipients === []) {
            $this->warn('Low-stock products detected, but no admin recipients are configured.');

            return self::SUCCESS;
        }

        foreach ($recipients as $recipient) {
            dispatch(new SendEmail(
                $recipient,
                new StoreLowStockAdminAlert($alertProducts),
            ))->onQueue('mail');
        }

        Product::query()
            ->whereIn('id', $alertProductIds)
            ->update([
                'low_stock_alert_sent_at' => now(),
            ]);

        $this->info('Queued '.count($recipients).' low-stock alert email(s) for '.count($alertProducts).' product(s).');

        return self::SUCCESS;
    }

    /**
     * @param  list<int>  $productIds
     * @return array<int, array{awaiting:int,reserved:int,backorder:int,preorder:int}>
     */
    private function inventorySummariesByProduct(array $productIds): array
    {
        if ($productIds === [] || ! Schema::hasTable('store_order_items')) {
            return [];
        }

        $summaries = collect($productIds)
            ->mapWithKeys(fn (int $productId) => [
                $productId => [
                    'awaiting' => 0,
                    'reserved' => 0,
                    'backorder' => 0,
                    'preorder' => 0,
                ],
            ])
            ->all();

        $items = StoreOrderItem::query()
            ->with([
                'trackingEntries' => fn ($query) => $query->select([
                    'id',
                    'store_order_item_id',
                    'shipment_type',
                    'quantity',
                ]),
            ])
            ->whereIn('product_id', $productIds)
            ->get([
                'id',
                'product_id',
                'quantity',
                'available_now_quantity',
                'delayed_quantity',
                'delayed_fulfilment_type',
                'is_preorder',
                'inventory_reserved_quantity',
                'cancelled_available_quantity',
                'cancelled_delayed_quantity',
            ]);

        foreach ($items as $item) {
            $productId = (int) $item->product_id;
            if (! isset($summaries[$productId])) {
                continue;
            }

            $summaries[$productId]['awaiting'] += $item->remainingFulfillableQuantity();
            $summaries[$productId]['reserved'] += $item->reservedInventory();

            $remainingDelayedQuantity = $item->remainingDelayedQuantity();
            if ($remainingDelayedQuantity <= 0) {
                continue;
            }

            if ((bool) $item->is_preorder || (string) $item->delayed_fulfilment_type === 'preorder') {
                $summaries[$productId]['preorder'] += $remainingDelayedQuantity;
            } else {
                $summaries[$productId]['backorder'] += $remainingDelayedQuantity;
            }
        }

        return $summaries;
    }

    /**
     * @return list<string>
     */
    private function adminRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', '')) ?: [];

        return collect($configured)
            ->map(fn ($email) => trim((string) $email))
            ->filter(fn ($email) => $email !== '')
            ->unique()
            ->values()
            ->all();
    }
}
