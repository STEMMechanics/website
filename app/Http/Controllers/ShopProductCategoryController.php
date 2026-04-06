<?php

namespace App\Http\Controllers;

use App\Models\ProductCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShopProductCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.shop.category.index', [
            'categories' => ProductCategory::query()
                ->withCount('products')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.shop.category.edit', [
            'category' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);
        $slug = trim((string) ($validated['slug'] ?? ''));
        $name = trim((string) $validated['name']);

        $category = ProductCategory::query()->create([
            'name' => $name,
            'slug' => $slug !== '' ? $slug : ProductCategory::uniqueSlug($name),
            'icon_class' => trim((string) ($validated['icon_class'] ?? '')) ?: 'fa-solid fa-tag',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);

        session()->flash('message', 'Product category has been created.');
        session()->flash('message-title', 'Category created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.category.edit', $category);
    }

    public function edit(ProductCategory $category): View
    {
        return view('admin.shop.category.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, ProductCategory $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);
        $slug = trim((string) ($validated['slug'] ?? ''));
        $name = trim((string) $validated['name']);

        $category->fill([
            'name' => $name,
            'slug' => $slug !== ''
                ? $slug
                : (($category->slug !== '') ? $category->slug : ProductCategory::uniqueSlug($name, (int) $category->id)),
            'icon_class' => trim((string) ($validated['icon_class'] ?? '')) ?: 'fa-solid fa-tag',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
        ]);
        $category->save();

        session()->flash('message', 'Product category has been updated.');
        session()->flash('message-title', 'Category updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(ProductCategory $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            session()->flash('message', 'This category is assigned to products and cannot be deleted yet.');
            session()->flash('message-title', 'Delete blocked');
            session()->flash('message-type', 'danger');

            return redirect()->route('admin.shop.category.edit', $category);
        }

        $category->delete();

        session()->flash('message', 'Product category deleted.');
        session()->flash('message-title', 'Category deleted');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.shop.category.index');
    }

    public function moveUp(ProductCategory $category): RedirectResponse
    {
        if ($this->reorderCategory($category, -1)) {
            session()->flash('message', 'Category moved up.');
            session()->flash('message-title', 'Category updated');
            session()->flash('message-type', 'success');
        } else {
            session()->flash('message', 'Category is already at the top.');
            session()->flash('message-title', 'No change');
            session()->flash('message-type', 'info');
        }

        return redirect()->back();
    }

    public function moveDown(ProductCategory $category): RedirectResponse
    {
        if ($this->reorderCategory($category, 1)) {
            session()->flash('message', 'Category moved down.');
            session()->flash('message-title', 'Category updated');
            session()->flash('message-type', 'success');
        } else {
            session()->flash('message', 'Category is already at the bottom.');
            session()->flash('message-title', 'No change');
            session()->flash('message-type', 'info');
        }

        return redirect()->back();
    }

    private function validateCategory(Request $request, ?ProductCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('product_categories', 'name')->ignore($category?->id)],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('product_categories', 'slug')->ignore($category?->id)],
            'icon_class' => ['nullable', 'string', 'max:120'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);
    }

    private function reorderCategory(ProductCategory $category, int $direction): bool
    {
        return DB::transaction(function () use ($category, $direction): bool {
            $orderedCategories = ProductCategory::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->values();

            $currentIndex = $orderedCategories->search(fn (ProductCategory $item) => (int) $item->id === (int) $category->id);
            if ($currentIndex === false) {
                return false;
            }

            $swapIndex = $direction < 0 ? $currentIndex - 1 : $currentIndex + 1;
            if ($swapIndex < 0 || $swapIndex >= $orderedCategories->count()) {
                return false;
            }

            $swapped = $orderedCategories->all();
            $swapped[$currentIndex] = $orderedCategories[$swapIndex];
            $swapped[$swapIndex] = $orderedCategories[$currentIndex];

            collect($swapped)->values()->each(function (ProductCategory $item, int $index): void {
                $item->sort_order = ($index + 1) * 10;
                $item->save();
            });
            return true;
        });
    }
}
