<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class ProductAllocation
{
    public function scope(int $product, ?int $variant = null): string
    {
        return 'product:'.$product.':'.($variant ?? 0);
    }

    public function resolve(int $product, ?int $variant = null): ?array
    {
        $scopes = [$this->scope($product)];
        if ($variant) {
            array_unshift($scopes, $this->scope($product, $variant));
        }
        $configs = DB::table('finance_product_allocations')->whereIn('scope', $scopes)->get()->keyBy('scope');
        foreach ($scopes as $scope) {
            $config = $configs->get($scope);
            if (! $config) {
                continue;
            }
            $profile = $config->profile_id ? DB::table('finance_product_profiles')->where('id', $config->profile_id)->first() : null;
            $rules = json_decode((string) ($profile->rules ?? $config->rules ?? '{}'), true);

            return ['product_id' => $product, 'variant_id' => $variant, 'profile' => $profile?->name, 'updated_by' => $config->updated_by, 'rules' => $rules];
        }

        return null;
    }

    public function snapshot(InvoiceLine $line): ?array
    {
        if ($line->kind !== 'product') {
            return null;
        }
        $item = $line->exists ? DB::table('store_order_items')->where('invoice_line_id', $line->id)->first() : null;
        if (! $line->source_id && $item?->product_id) {
            $line->source_type = Product::class;
            $line->source_id = $item->product_id;
        }
        if ($line->source_type !== Product::class || ! $line->source_id) {
            return null;
        }
        $variant = data_get($line->details_json, 'store_context.variant_id') ?? data_get($line->details_json, 'variant_id') ?? $item?->product_variant_id;

        return $this->resolve((int) $line->source_id, $variant ? (int) $variant : null);
    }

    /** Allocate cents cumulatively, conserving rounding and never exceeding revenue. */
    public function split(int $amount, array $weights): array
    {
        $result = [];
        $total = array_sum($weights);
        $sum = $previous = 0;
        foreach ($weights as $id => $weight) {
            $sum += max(0, $weight);
            $next = $total > 0 ? (int) round(max(0, $amount) * $sum / $total) : 0;
            $result[$id] = $next - $previous;
            $previous = $next;
        }

        return $result;
    }

    public function targets(array $rules, float $quantity, int $revenue): array
    {
        $fixed = [];
        foreach ($rules['fixed'] ?? [] as $id => $cents) {
            $fixed[$id] = (int) round($cents * max(0, $quantity));
        }
        $revenue = max(0, $revenue);
        $targets = array_sum($fixed) > $revenue ? $this->split($revenue, $fixed) : $fixed;
        $margin = max(0, $revenue - array_sum($fixed));
        $percent = $rules['percent'] ?? [];
        $percent['surplus'] = 10000 - array_sum($percent);
        foreach ($this->split($margin, $percent) as $id => $cents) {
            if ($id !== 'surplus') {
                $targets[$id] = ($targets[$id] ?? 0) + $cents;
            }
        }

        return array_filter($targets);
    }

    /** Product line prices include their proportional share of invoice-wide discounts. */
    public function lines(Invoice $invoice): array
    {
        $invoice->loadMissing('lines');
        $planner = app(FinancePlanner::class);
        $weights = $invoice->lines->mapWithKeys(fn ($line) => [$line->line_number => max(0, $planner->cents($line->line_total_ex_tax))])->all();
        $revenues = $this->split(max(0, $planner->cents($invoice->total_amount) - $planner->cents($invoice->gst_amount)), $weights);
        $lines = [];
        foreach ($invoice->lines as $line) {
            if ($line->kind !== 'product' || ! $line->product_allocation_snapshot) {
                continue;
            }
            $key = $invoice->id.':'.$line->line_number;
            $revenue = $revenues[$line->line_number] ?? 0;
            $lines[$key] = ['revenue' => $revenue, 'targets' => $this->targets($line->product_allocation_snapshot['rules'], (float) $line->quantity, $revenue)];
        }

        return $lines;
    }

    public function balances(array $events): array
    {
        $balances = [];
        foreach ($events as $event) {
            foreach ($event['line_net'] ?? [] as $key => $amount) {
                $balances[$key] = ($balances[$key] ?? 0) + $amount;
            }
        }

        return $balances;
    }

    public function funding(array $targets, int $net, array $rounding, $categories): array
    {
        $targetTotal = array_sum($targets);
        $funded = [];
        $productNet = 0;
        $receivedLines = [];
        foreach ($rounding['product_lines'] as $key => $line) {
            $receivedLines[$key] = max(0, min($line['revenue'], $rounding['received_lines'][$key] ?? 0));
        }
        if (array_sum($receivedLines) > max(0, $net)) {
            $receivedLines = $this->split(max(0, $net), $receivedLines);
        }
        foreach ($rounding['product_lines'] as $key => $line) {
            $received = $receivedLines[$key];
            $productNet += $received;
            $weights = $line['targets'];
            $weights['surplus'] = max(0, $line['revenue'] - array_sum($weights));
            foreach ($line['targets'] as $id => $amount) {
                $targets[$id] = max(0, ($targets[$id] ?? 0) - $amount);
            }
            foreach ($this->split($received, $weights) as $id => $amount) {
                if ($id !== 'surplus') {
                    $funded[$id] = ($funded[$id] ?? 0) + $amount;
                }
            }
        }
        unset($rounding['product_lines'], $rounding['received_lines']);
        $rest = app(FinancePlanner::class)->funding($targets, max(0, $net - $productNet), $rounding, $categories);
        foreach ($funded as $id => $amount) {
            $rest['categories'][$id] = ($rest['categories'][$id] ?? 0) + $amount;
        }
        $rest['shortfall'] = max(0, $targetTotal - $net);
        $rest['surplus'] = max(0, $net - array_sum($rest['categories']));

        return $rest;
    }
}
