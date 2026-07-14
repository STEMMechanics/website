<?php

namespace App\Http\Controllers;

use App\Models\WorkshopCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WorkshopCategoryController extends Controller
{
    public function index(): View
    {
        return view('admin.workshop-category.index', [
            'categories' => WorkshopCategory::query()
                ->withCount('workshops')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function create(): View
    {
        return view('admin.workshop-category.edit', [
            'category' => null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateCategory($request);
        $name = trim((string) $validated['name']);
        $slug = trim((string) ($validated['slug'] ?? ''));

        WorkshopCategory::query()->create([
            'name' => $name,
            'slug' => $slug !== '' ? $slug : WorkshopCategory::uniqueSlug($name),
            'icon_class' => trim((string) ($validated['icon_class'] ?? '')) ?: 'fa-solid fa-tag',
            'hide_in_footer' => $request->boolean('hide_in_footer'),
        ]);

        session()->flash('message', 'Workshop category has been created.');
        session()->flash('message-title', 'Category created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop-category.index');
    }

    public function edit(WorkshopCategory $category): View
    {
        return view('admin.workshop-category.edit', [
            'category' => $category,
        ]);
    }

    public function update(Request $request, WorkshopCategory $category): RedirectResponse
    {
        $validated = $this->validateCategory($request, $category);
        $name = trim((string) $validated['name']);
        $slug = trim((string) ($validated['slug'] ?? ''));

        $category->fill([
            'name' => $name,
            'slug' => $slug !== ''
                ? $slug
            : (($category->slug !== '') ? $category->slug : WorkshopCategory::uniqueSlug($name, (int) $category->id)),
            'icon_class' => trim((string) ($validated['icon_class'] ?? '')) ?: 'fa-solid fa-tag',
            'hide_in_footer' => $request->boolean('hide_in_footer'),
        ]);
        $category->save();

        session()->flash('message', 'Workshop category has been updated.');
        session()->flash('message-title', 'Category updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop-category.index');
    }

    public function destroy(Request $request, WorkshopCategory $category): RedirectResponse
    {
        $validated = $request->validate([
            'reassign_category_id' => [
                'nullable',
                'integer',
                Rule::exists('workshop_categories', 'id')->where(fn ($query) => $query->where('id', '!=', $category->id)),
            ],
        ]);

        $reassignCategoryId = (int) ($validated['reassign_category_id'] ?? 0);

        DB::transaction(function () use ($category, $reassignCategoryId): void {
            if ($reassignCategoryId > 0) {
                $workshopIds = $category->workshops()->pluck('workshops.id')->all();
                $targetCategory = WorkshopCategory::query()->findOrFail($reassignCategoryId);

                $targetCategory->workshops()->syncWithoutDetaching($workshopIds);
            }

            $category->workshops()->detach();
            $category->delete();
        });

        session()->flash('message', $reassignCategoryId > 0
            ? 'Workshop category deleted and assigned workshops were moved.'
            : 'Workshop category deleted.');
        session()->flash('message-title', 'Category deleted');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.workshop-category.index');
    }

    private function validateCategory(Request $request, ?WorkshopCategory $category = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('workshop_categories', 'name')->ignore($category?->id)],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('workshop_categories', 'slug')->ignore($category?->id)],
            'icon_class' => ['nullable', 'string', 'max:120'],
            'hide_in_footer' => ['nullable', 'boolean'],
        ]);
    }
}
