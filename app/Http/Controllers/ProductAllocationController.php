<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Models\Product;
use App\Services\Finance\FinancePlanner;
use App\Services\Finance\InvoiceAllocation;
use App\Services\Finance\ProductAllocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProductAllocationController extends Controller
{
    public function profiles(Request $request): View
    {
        $request->validate(['edit' => 'nullable|integer|exists:finance_product_profiles,id']);

        return view('admin.cost-centre.product-profiles', [
            'profiles' => DB::table('finance_product_profiles')->orderBy('name')->get(),
            'editing' => $request->filled('edit') ? DB::table('finance_product_profiles')->find($request->integer('edit')) : null,
            'categories' => $this->categories(),
        ]);
    }

    public function saveProfile(Request $request): RedirectResponse
    {
        $data = $request->validate(['id' => 'nullable|integer|exists:finance_product_profiles,id', 'name' => 'required|string|max:100']);
        $values = ['name' => $data['name'], 'rules' => json_encode($this->rules($request)), 'updated_at' => now()];
        if (! empty($data['id'])) {
            DB::table('finance_product_profiles')->where('id', $data['id'])->update($values);
        } else {
            DB::table('finance_product_profiles')->insert($values + ['created_at' => now()]);
        }

        return redirect()->route('admin.product-allocation.profiles')->with('message', 'Product allocation profile saved. Existing invoice allocations are unchanged.')->with('message-type', 'success');
    }

    public function edit(Request $request, Product $product): View
    {
        $request->validate(['variant' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)]]);

        return view('admin.shop.product.allocations', [
            'variantId' => $request->filled('variant') ? $request->integer('variant') : null,
            'product' => $product->load('variants'),
            'configs' => DB::table('finance_product_allocations')->where('product_id', $product->id)->get()->keyBy('scope'),
            'profiles' => DB::table('finance_product_profiles')->orderBy('name')->get(),
            'categories' => $this->categories(),
        ]);
    }

    public function save(Request $request, Product $product, ProductAllocation $allocations): RedirectResponse
    {
        $data = $request->validate([
            'variant_id' => ['nullable', 'integer', Rule::exists('product_variants', 'id')->where('product_id', $product->id)],
            'mode' => ['required', Rule::in(['inherit', 'profile', 'custom'])],
            'profile_id' => ['nullable', 'required_if:mode,profile', 'integer', 'exists:finance_product_profiles,id'],
        ]);
        $variant = isset($data['variant_id']) ? (int) $data['variant_id'] : null;
        $scope = $allocations->scope($product->id, $variant);
        if ($data['mode'] === 'inherit') {
            DB::table('finance_product_allocations')->where('scope', $scope)->delete();
        } else {
            $rules = $data['mode'] === 'custom' ? $this->rules($request) : null;
            DB::table('finance_product_allocations')->updateOrInsert(['scope' => $scope], [
                'product_id' => $product->id, 'variant_id' => $variant, 'updated_by' => $request->user()->id,
                'profile_id' => $data['mode'] === 'profile' ? $data['profile_id'] : null,
                'rules' => $rules === null ? null : json_encode($rules), 'updated_at' => now(), 'created_at' => now(),
            ]);
        }

        return redirect()->route('admin.product-allocation.edit', ['product' => $product, 'variant' => $variant])->with('message', 'Product allocation saved. Existing invoice allocations are unchanged.')->with('message-type', 'success');
    }

    public function apply(Invoice $invoice, Request $request, ProductAllocation $products, InvoiceAllocation $allocations): RedirectResponse
    {
        DB::transaction(function () use ($invoice, $request, $products, $allocations): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            foreach ($invoice->lines()->where('kind', 'product')->get() as $line) {
                $line->product_allocation_snapshot = $products->snapshot($line) ?? [];
                $line->save();
            }
            $context = $allocations->context($invoice->fresh());
            if ($context['automaticWarning'] || ! $context['suggestedTargets']) {
                throw ValidationException::withMessages(['targets' => $context['automaticWarning'] ?? 'Set allocation rules on the linked products first.']);
            }
            $allocations->sync($invoice->fresh(), $request->user()->id, true);
        });

        return redirect()->route('admin.invoice.edit', $invoice)->with('message', 'Current product allocations applied.')->with('message-type', 'success');
    }

    private function categories()
    {
        return DB::table('finance_categories')->where('active', true)->whereIn('kind', ['cost', 'owner'])->orderBy('priority')->orderBy('id')->get();
    }

    private function rules(Request $request): array
    {
        $data = $request->validate(['fixed' => 'nullable|array', 'fixed.*' => 'nullable|numeric|min:0|max:1000000|decimal:0,2', 'percent' => 'nullable|array', 'percent.*' => 'nullable|numeric|min:0|max:100|decimal:0,2']);
        $planner = app(FinancePlanner::class);
        $rules = ['fixed' => array_filter(array_map($planner->cents(...), $data['fixed'] ?? [])), 'percent' => array_filter(array_map($planner->cents(...), $data['percent'] ?? []))];
        $ids = array_unique(array_merge(array_keys($rules['fixed']), array_keys($rules['percent'])));
        if (array_diff($ids, $this->categories()->pluck('id')->all())) {
            throw ValidationException::withMessages(['fixed' => 'Choose active cost centres.']);
        }
        if (array_sum($rules['percent']) > 10000) {
            throw ValidationException::withMessages(['percent' => 'The remainder percentages cannot exceed 100%.']);
        }
        ksort($rules['fixed']);
        ksort($rules['percent']);

        return $rules;
    }
}
