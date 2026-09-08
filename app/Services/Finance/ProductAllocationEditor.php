<?php

namespace App\Services\Finance;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductAllocationEditor
{
    public function categories(): Collection
    {
        return DB::table('finance_categories')->where('active', true)->whereIn('kind', ['cost', 'owner'])->orderBy('priority')->orderBy('id')->get();
    }

    public function data(?Product $product): array
    {
        $configs = $product ? $this->configs([$product->id]) : collect();
        $base = $configs->get(app(ProductAllocation::class)->scope($product->id ?? 0));
        $variants = [];
        foreach ($product->variants ?? [] as $variant) {
            $config = $configs->get(app(ProductAllocation::class)->scope($product->id, $variant->id));
            $variants[$variant->id] = ['inherit' => ! $config, 'rules' => $config ? $this->rules($config) : []];
        }

        return ['base' => $base ? $this->rules($base) : [], 'variants' => $variants];
    }

    /** Validate before changing the product or its variants. Values are integer cents / basis points. */
    public function validate(Request $request): ?array
    {
        if (! $request->has('product_allocations_json')) {
            return null;
        }
        $data = json_decode((string) $request->input('product_allocations_json'), true);
        $validator = validator(['allocation' => $data], [
            'allocation' => 'required|array:base,variants',
            'allocation.base' => 'required|array:fixed,percent',
            'allocation.variants' => 'present|array',
            'allocation.variants.*' => 'required|array:inherit,rules',
            'allocation.variants.*.inherit' => 'required|boolean',
            'allocation.variants.*.rules' => 'present|array:fixed,percent',
        ]);
        if ($validator->fails()) {
            throw ValidationException::withMessages(['product_allocations_json' => 'Check the cost centre allocations and try again.']);
        }
        $allowed = $this->categories()->pluck('id')->all();
        $validateRules = function (array $rules) use ($allowed): array {
            $validator = validator($rules, ['fixed' => 'present|array', 'fixed.*' => 'integer|min:0|max:100000000', 'percent' => 'present|array', 'percent.*' => 'integer|min:0|max:10000']);
            if ($validator->fails() || array_diff(array_merge(array_keys($rules['fixed'] ?? []), array_keys($rules['percent'] ?? [])), $allowed)
                || array_sum($rules['percent'] ?? []) > 10000) {
                throw ValidationException::withMessages(['product_allocations_json' => 'Use valid cost centres and positive amounts. Remainder percentages must total no more than 100%.']);
            }
            $rules = ['fixed' => array_filter($rules['fixed']), 'percent' => array_filter($rules['percent'])];
            ksort($rules['fixed']);
            ksort($rules['percent']);

            return $rules;
        };
        $data['base'] = $validateRules($data['base']);
        $submittedVariants = $request->input('variants', []);
        if (! is_array($submittedVariants)) {
            throw ValidationException::withMessages(['variants' => 'Check the product variants and try again.']);
        }
        foreach ($data['variants'] as $index => &$variant) {
            if (! array_key_exists($index, $submittedVariants)) {
                throw ValidationException::withMessages(['product_allocations_json' => 'An allocation refers to a removed variant. Refresh the editor and try again.']);
            }
            $variant['rules'] = $validateRules($variant['rules']);
        }

        return $data;
    }

    /** Variant IDs come from the saved, ownership-checked product rows, never the allocation payload. */
    public function save(Product $product, array $variants, ?array $data, string $userId): void
    {
        if ($data === null) {
            return;
        }
        $this->saveRules($product->id, null, $data['base'], $userId);
        foreach ($variants as $index => $variant) {
            $allocation = $data['variants'][$index] ?? ['inherit' => true];
            if ($allocation['inherit']) {
                DB::table('finance_product_allocations')->where('scope', app(ProductAllocation::class)->scope($product->id, $variant->id))->delete();
            } else {
                $this->saveRules($product->id, $variant->id, $allocation['rules'], $userId);
            }
        }
    }

    private function saveRules(int $productId, ?int $variantId, array $rules, string $userId): void
    {
        DB::table('finance_product_allocations')->updateOrInsert(['scope' => app(ProductAllocation::class)->scope($productId, $variantId)], [
            'product_id' => $productId, 'variant_id' => $variantId, 'profile_id' => null, 'rules' => json_encode($rules),
            'updated_by' => $userId, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /** Bulk reads keep the product listing independent of the number of cost centres and variants. */
    public function attentionIds(): array
    {
        $ids = [];
        $allowed = $this->categories()->pluck('id')->all();
        Product::query()->with('variants')->chunkById(200, function ($products) use (&$ids, $allowed): void {
            $configs = $this->configs($products->modelKeys());
            $allocations = app(ProductAllocation::class);
            foreach ($products as $product) {
                $base = $configs->get($allocations->scope($product->id));
                $options = [['price' => $product->price, 'config' => $base]];
                foreach ($product->variants->where('is_active', true) as $variant) {
                    $options[] = ['price' => $variant->price ?? $product->price, 'config' => $configs->get($allocations->scope($product->id, $variant->id)) ?? $base];
                }
                foreach ($options as $option) {
                    $net = (int) round((float) $option['price'] * 100 / (1 + (float) $product->tax_rate));
                    $rules = $option['config'] ? $this->rules($option['config']) : ['fixed' => [], 'percent' => []];
                    $targets = $allocations->targets($rules, 1, $net);
                    $activeTargets = array_intersect_key($targets, array_flip($allowed));
                    if ($net > 0 && (array_sum($activeTargets) < $net || array_sum($rules['fixed'] ?? []) > $net)) {
                        $ids[] = $product->id;
                        break;
                    }
                }
            }
        });

        return $ids;
    }

    private function configs(array $productIds): Collection
    {
        return DB::table('finance_product_allocations as a')->leftJoin('finance_product_profiles as p', 'p.id', '=', 'a.profile_id')
            ->whereIn('a.product_id', $productIds)->select('a.*', 'p.rules as profile_rules')->get()->keyBy('scope');
    }

    private function rules(object $config): array
    {
        return json_decode($config->profile_rules ?? $config->rules ?? '{}', true);
    }
}
