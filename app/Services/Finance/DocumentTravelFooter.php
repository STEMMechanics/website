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
        $mixedGroupedTravelRate = false;
        $hasUnpricedTravelLine = false;
        foreach ($items as $item) {
            if (! in_array(($item['kind'] ?? ''), ['travel', 'multi_travel'], true)) continue;
            $details = $item['details_json'] ?? [];
            $tax = GstRate::forLine($item);
            if (($item['kind'] ?? '') === 'multi_travel') {
                $travelRows = array_values(array_filter(
                    $details['multi_travel']['rows'] ?? [],
                    fn ($row) => is_array($row) && ($row['travel_hours'] ?? '') !== '',
                ));
                $supplyProfiles = collect($travelRows)->map(function (array $row): string {
                    $supplied = $row['supplied_categories'] ?? data_get($row, 'details_json.travel.supplied_categories', []);
                    if (is_array($supplied)) {
                        ksort($supplied);
                    }

                    return json_encode($supplied) ?: '[]';
                })->unique()->count();
                $mixedGroupedTravelRate = $mixedGroupedTravelRate || $supplyProfiles > 1;
                $rate = isset($details['inclusive_unit_price'])
                    ? (float) $details['inclusive_unit_price'] / (1 + $tax)
                    : (float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0);
                $quantityBasis = $details['multi_travel']['quantity_basis'] ?? '';
                $rate = round($rate / ($quantityBasis === 'hours' ? 4 : 1), 2);
                if ($rate > 0) {
                    $rates[] = $rate;
                } else {
                    $hasUnpricedTravelLine = true;
                }
                continue;
            }
            $rate = isset($details['inclusive_unit_price'])
                ? (float) $details['inclusive_unit_price'] / (1 + $tax)
                : (float) ($item['unit_price_ex_tax'] ?? $item['unit_price'] ?? 0);
            $quarterUnits = isset($details['travel']['billable_units']) && ($details['travel']['quantity_basis'] ?? '') !== 'hours';
            $rate = round($rate / ($quarterUnits ? 1 : 4), 2);
            if ($rate > 0) {
                $rates[] = $rate;
            } else {
                $hasUnpricedTravelLine = true;
            }
        }
        $hasPricedTravelLine = $rates !== [];
        // Use the plan saved on the document; never apply today's default to a PDF.
        if ($rates === [] && $document !== null) {
            $plan = $document->pricing_version_id
                ? DB::table('finance_pricing_versions')->where('id', $document->pricing_version_id)->select('rules', 'prices')->first()
                : null;
            if (! $plan && $document instanceof Invoice) {
                // Older invoices can still use their retained finance allocation plan.
                $plan = DB::table('finance_budgets')->join('finance_pricing_versions', 'finance_pricing_versions.id', '=', 'finance_budgets.pricing_version_id')
                    ->join('finance_budget_invoices', 'finance_budget_invoices.budget_id', '=', 'finance_budgets.id')->where('finance_budget_invoices.invoice_id', $document->id)->select('finance_pricing_versions.rules', 'finance_pricing_versions.prices')->first();
            }
            if ($plan) {
                $rules = array_values(array_filter(json_decode($plan->rules, true) ?: [], fn ($rule) => ($rule['basis'] ?? '') === 'travel'));
                $prices = json_decode($plan->prices, true) ?: [];
                $lineTaxes = collect($items)->map(fn ($item) => GstRate::forLine($item));
                $tax = $lineTaxes->first(fn ($rate) => $rate > 0);
                $tax ??= $items !== [] ? 0.0 : GstRate::forDate($document instanceof Invoice ? $document->issue_date : $document->quote_date);
                $gross = $rules !== []
                    ? array_sum(array_column($rules, 'rate_cents')) * (1 + $tax)
                    : (float) ($prices['travel_cents'] ?? 0);
                $step = (int) ($prices['travel_rounding_step'] ?? 0);
                if ($step > 0) $gross = ceil(($gross - 0.000001) / $step) * $step;
                if ($gross > 0) {
                    $rates[] = round($gross / (100 * (1 + $tax)), 2);
                }
            }
        }
        $rates = array_unique($rates);
        if ($mixedGroupedTravelRate || ($hasUnpricedTravelLine && $hasPricedTravelLine) || count($rates) !== 1) return 'Travel charges are as itemised or otherwise agreed.';
        return str_replace('{travel_rate_ex_gst}', '$'.number_format((float) reset($rates), 2), $template);
    }
}
