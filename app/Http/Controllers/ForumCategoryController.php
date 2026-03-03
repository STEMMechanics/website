<?php

namespace App\Http\Controllers;

use App\Models\ForumCategory;
use App\Models\UserGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ForumCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ForumCategory::query()
            ->withCount(['topics', 'posts'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.forum.category.index', [
            'categories' => $categories,
            'groupSuggestions' => $this->groupSuggestions(),
        ]);
    }

    public function save(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'categories' => ['nullable', 'array'],
            'categories.*.id' => ['nullable', 'uuid', 'exists:forum_categories,id'],
            'categories.*.name' => ['nullable', 'string', 'max:120'],
            'categories.*.description' => ['nullable', 'string', 'max:2000'],
            'categories.*.icon_class' => ['nullable', 'string', 'max:120'],
            'categories.*.color_hex' => ['nullable', 'string', 'regex:/^#?[0-9A-Fa-f]{6}$/'],
            'categories.*.read_group_slug' => ['nullable', 'string', 'max:80'],
            'categories.*.write_group_slug' => ['nullable', 'string', 'max:80'],
            'categories.*.is_divider' => ['nullable', 'boolean'],
            'deleted_category_ids' => ['nullable', 'array'],
            'deleted_category_ids.*' => ['uuid', 'exists:forum_categories,id'],
        ]);

        $rows = collect($validated['categories'] ?? [])
            ->map(function (array $row): array {
                $isDivider = filter_var($row['is_divider'] ?? false, FILTER_VALIDATE_BOOLEAN);

                return [
                    'id' => trim((string) ($row['id'] ?? '')) ?: null,
                    'name' => trim((string) ($row['name'] ?? '')),
                    'description' => trim((string) ($row['description'] ?? '')) ?: null,
                    'icon_class' => ForumCategory::normalizeIconClass($row['icon_class'] ?? ''),
                    'color_hex' => ForumCategory::normalizeColorHex($row['color_hex'] ?? ''),
                    'read_group_slug' => ForumCategory::normalizeGroupSlug($row['read_group_slug'] ?? ''),
                    'write_group_slug' => ForumCategory::normalizeGroupSlug($row['write_group_slug'] ?? ''),
                    'is_divider' => $isDivider,
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values();

        $deletedIds = collect($validated['deleted_category_ids'] ?? [])
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn (string $id) => $id !== '')
            ->unique()
            ->values();

        $categoriesById = ForumCategory::query()
            ->whereIn('id', $rows->pluck('id')->filter()->all())
            ->get()
            ->keyBy(fn (ForumCategory $category) => (string) $category->id);

        $usedSlugs = ForumCategory::query()
            ->when($deletedIds->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $deletedIds->all()))
            ->pluck('slug')
            ->map(fn ($slug) => trim((string) $slug))
            ->filter(fn (string $slug) => $slug !== '')
            ->values()
            ->all();

        $savedIds = [];

        $rows->each(function (array $row, int $index) use ($categoriesById, &$usedSlugs, &$savedIds): void {
            /** @var ForumCategory|null $category */
            $category = $row['id'] ? $categoriesById->get($row['id']) : null;

            if (! $category) {
                $category = new ForumCategory();
                $category->slug = $row['is_divider']
                    ? $this->nextAvailableDividerSlug($usedSlugs)
                    : $this->nextAvailableSlug($row['name'], $usedSlugs);
                $usedSlugs[] = $category->slug;
            }

            $category->name = $row['name'];
            $category->description = $row['is_divider'] ? null : $row['description'];
            $category->icon_class = $row['is_divider'] ? null : $row['icon_class'];
            $category->color_hex = $row['is_divider'] ? null : $row['color_hex'];
            $category->read_group_slug = $row['is_divider'] ? null : $row['read_group_slug'];
            $category->write_group_slug = $row['is_divider'] ? null : $row['write_group_slug'];
            $category->sort_order = ($index + 1) * 10;
            $category->save();

            $savedIds[] = (string) $category->id;
        });

        if ($deletedIds->isNotEmpty()) {
            ForumCategory::query()
                ->whereIn('id', $deletedIds->all())
                ->delete();
        }

        session()->flash('message', 'Discussion categories updated.');
        session()->flash('message-title', 'Categories updated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.forum.category.index');
    }

    private function nextAvailableSlug(string $name, array $usedSlugs): string
    {
        $base = ForumCategory::normalizeSlug($name);
        $base = $base !== '' ? $base : 'category';
        $slug = $base;
        $suffix = 2;

        while (in_array($slug, $usedSlugs, true)) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    private function nextAvailableDividerSlug(array $usedSlugs): string
    {
        $length = 1;

        while (true) {
            $candidate = str_repeat('-', $length);
            if (! in_array($candidate, $usedSlugs, true)) {
                return $candidate;
            }
            $length++;
        }
    }

    private function groupSuggestions(): array
    {
        return UserGroup::query()
            ->orderBy('slug')
            ->distinct()
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->filter(fn ($slug) => $slug !== '')
            ->values()
            ->all();
    }
}
