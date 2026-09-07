<?php

namespace App\Http\Controllers;

use App\Models\Supplier;
use App\Services\SiteListControls;
use App\Support\ListPageSize;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['search' => 'nullable|string|max:255', 'cost_centre_id' => ['nullable', 'integer', Rule::exists('finance_categories', 'id')->where('kind', 'cost')]]);
        $search = $filters['search'] ?? '';
        $query = Supplier::query()->withCount('expenses')->withSum('expenses', 'total_amount');
        if (isset($filters['cost_centre_id'])) {
            $query->where('category_id', $filters['cost_centre_id']);
        }
        if ($search !== '') {
            $query->where('name', 'like', '%'.$search.'%');
        }
        app(SiteListControls::class)->apply($query);
        $suppliers = $query->orderBy('name')->paginate(ListPageSize::resolve(25))->withQueryString();

        return view('admin.supplier.index', ['suppliers' => $suppliers, 'categories' => DB::table('finance_categories')->pluck('name', 'id')]);
    }

    public function create(): View
    {
        return $this->editor(new Supplier);
    }

    public function edit(Supplier $supplier): View
    {
        return $this->editor($supplier);
    }

    private function editor(Supplier $supplier): View
    {
        $categories = DB::table('finance_categories')->where('kind', 'cost')->where(function ($query) use ($supplier): void {
            $query->where('active', true)->orWhere('id', $supplier->category_id);
        })->orderBy('name')->get();

        return view('admin.supplier.edit', compact('supplier', 'categories'));
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        return $this->save($request, new Supplier);
    }

    public function update(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        return $this->save($request, $supplier);
    }

    private function save(Request $request, Supplier $supplier): RedirectResponse|JsonResponse
    {
        $name = $request->validate(['name' => 'required|string|max:255'])['name'];
        $request->merge(['name' => trim($name), 'supplier' => mb_strtolower(trim($name))]);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'supplier' => ['required', Rule::unique('finance_supplier_rules', 'supplier')->ignore($supplier->id)],
            'category_id' => ['required', 'integer', Rule::exists('finance_categories', 'id')->where(fn ($query) => $query->where('kind', 'cost')->where(fn ($active) => $active->where('active', true)->orWhere('id', $supplier->category_id)))],
        ], ['supplier.unique' => 'This supplier already exists. Open its supplier page to set the default cost centre.']);
        DB::transaction(function () use ($supplier, $data): void {
            DB::table('finance_settings')->where('id', 1)->lockForUpdate()->first();
            $supplier->fill($data + ['mode' => 'default', 'splits' => [$data['category_id'] => 100]])->save();
        });

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Supplier saved.']);
        }

        return redirect()->route('admin.supplier.show', $supplier)->with('message', 'Supplier saved. Expenses without an override use this cost centre.')->with('message-type', 'success');
    }
}
