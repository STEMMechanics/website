<?php

namespace App\Services\Finance;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SiteOption;
use App\Services\ProductRecipeService;
use App\Support\RequestMemo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductCostAllocation
{
    public const REMAINDER_OPTION = 'store.costing.remainder-allocation';

    /** @return array<string, int> */
    public function centres(): array
    {
        return app(RequestMemo::class)->remember(__METHOD__, fn (): array => DB::table('finance_categories')
            ->where('active', true)->whereIn('kind', ['cost', 'owner'])->get()
            ->mapWithKeys(fn ($category): array => [mb_strtolower(trim($category->name)) => (int) $category->id])->all());
    }

    /** Percentages are stored as integer basis points, as in product allocation rules. */
    public function parseRemainder(?string $json, string $field, bool $inherit = false): ?array
    {
        $rules = json_decode($json ?? '', true);
        if ($inherit && trim($json ?? '') === 'null') {
            return null;
        }
        $allowed = app(ProductAllocationEditor::class)->categories()->pluck('id')->all();
        if (! is_array($rules) || array_diff(array_keys($rules), $allowed)
            || collect($rules)->contains(fn ($value): bool => ! is_int($value) || $value < 0 || $value > 10000)
            || array_sum($rules) > 10000) {
            throw ValidationException::withMessages([$field => 'Choose active cost centres and percentages totalling no more than 100%.']);
        }
        $rules = array_filter($rules);
        ksort($rules);

        return $rules;
    }

    public function defaultRemainder(): array
    {
        $rules = json_decode((string) SiteOption::value(self::REMAINDER_OPTION, '{}'), true);

        return is_array($rules) ? $rules : [];
    }

    /** A receipt-wide split cannot identify individual lines on a mixed receipt. */
    public function purchaseCentre(Product $product, ?ProductVariant $variant = null): string
    {
        $costing = app(ProductRecipeService::class);
        $purchase = $costing->purchaseSource($product, $variant);
        if (! $purchase?->expense || $costing->purchaseCost($product, $variant) === null) {
            return 'stock';
        }
        $splits = array_filter(app(FinancePlanner::class)->expenseSplits($purchase->expense), fn ($cents): bool => $cents > 0);
        if (count($splits) === 1) {
            foreach (['stock', 'consumables'] as $name) {
                if (array_key_first($splits) === ($this->centres()[$name] ?? null)) {
                    return $name;
                }
            }
        }

        return 'stock';
    }

    /** Null indicates missing costs or a required cost centre that is no longer active. */
    public function rules(Product $product, ?ProductVariant $variant = null): ?array
    {
        $costing = app(ProductRecipeService::class);
        $costs = $costing->materialCostsByCentre($product, $variant);
        if ($costs === null) {
            return null;
        }
        $costs['operational'] = $costing->operationalCost($product);
        $costs['owner remuneration'] = $costing->timeCost($product);
        $centres = $this->centres();
        $fixed = [];
        // Round the combined material cost once, conserving cents across both centres.
        $materials = array_intersect_key($costs, array_flip(['stock', 'consumables']));
        $materialCents = app(ProductAllocation::class)->split((int) round(array_sum($materials) * 100), $materials);
        foreach ($costs as $name => $amount) {
            if (! is_finite($amount) || $amount < 0 || $amount > 10000000 || $amount > 0 && ! isset($centres[$name])) {
                return null;
            }
            if (isset($centres[$name])) {
                $fixed[$centres[$name]] = $materialCents[$name] ?? (int) round($amount * 100);
            }
        }
        $percent = $product->allocation_remainder ?? $this->defaultRemainder();
        if (array_diff(array_keys($percent), array_values($centres))
            || collect($percent)->contains(fn ($value): bool => ! is_int($value) || $value < 0 || $value > 10000)
            || array_sum($percent) > 10000) {
            return null;
        }

        return ['fixed' => array_filter($fixed), 'percent' => $percent];
    }
}
