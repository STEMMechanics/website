<?php

namespace App\Services\Finance;

use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Support\Facades\DB;

class DocumentTravelFooter
{
    public static function render(string $template, Invoice|Quote|null $document): string
    {
        if (! str_contains($template, '{travel_rate_ex_gst}')) return $template;
        $items = $document instanceof Invoice ? $document->lines->toArray() : ($document->line_items ?? []);
        $rates = [];
        foreach ($items as $item) {
            if (($item['kind'] ?? '') !== 'travel') continue;
            $details = $item['details_json'] ?? [];
            $tax = GstRate::forLine($item);
            $rate = isset($details['inclusive_unit_price'])
                ? (float) $details['inclusive_unit_price'] / (1 + $tax)
                : (float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0);
            $quarterUnits = isset($details['travel']['billable_units']) && ($details['travel']['quantity_basis'] ?? '') !== 'hours';
            $rates[] = round($rate / ($quarterUnits ? 1 : 4), 2);
        }
        // Only a retained plan is safe for historical documents; never use today's default.
        if ($rates === [] && $document instanceof Invoice) {
            $plan = DB::table('finance_budgets')->join('finance_pricing_versions', 'finance_pricing_versions.id', '=', 'finance_budgets.pricing_version_id')
                ->join('finance_budget_invoices', 'finance_budget_invoices.budget_id', '=', 'finance_budgets.id')->where('finance_budget_invoices.invoice_id', $document->id)->select('finance_pricing_versions.rules', 'finance_pricing_versions.prices')->first();
            if ($plan) {
                $rules = array_filter(json_decode($plan->rules, true), fn ($rule) => $rule['basis'] === 'travel');
                $prices = json_decode($plan->prices, true);
                if ($rules) {
                    $tax = (float) ($document->lines->first(fn ($line) => (float) $line->tax_rate > 0)->tax_rate ?? GstRate::LEGACY);
                    $gross = array_sum(array_column($rules, 'rate_cents')) * (1 + $tax);
                    $step = (int) ($prices['travel_rounding_step'] ?? 0);
                    if ($step > 0) $gross = ceil(($gross - 0.000001) / $step) * $step;
                    $rates[] = round($gross / (100 * (1 + $tax)), 2);
                }
            }
        }
        $rates = array_unique($rates);
        if (count($rates) !== 1) return 'Travel charges are as itemised or otherwise agreed.';
        return str_replace('{travel_rate_ex_gst}', '$'.number_format((float) reset($rates), 2), $template);
    }
}
