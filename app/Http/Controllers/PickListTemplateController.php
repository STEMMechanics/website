<?php

namespace App\Http\Controllers;

use App\Models\PickListTemplate;
use App\Models\PickListTemplateItem;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PickListTemplateController extends Controller
{
    public function index(Request $request)
    {
        $query = PickListTemplate::query()->withCount('items');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search', ''));
            $query->where(function ($builder) use ($search): void {
                $builder->where('name', 'like', '%'.$search.'%')
                    ->orWhere('description', 'like', '%'.$search.'%');
            });
        }

        $templates = $query->orderBy('name')->paginate(20)->onEachSide(1);

        return view('admin.pick-list-template.index', [
            'templates' => $templates,
        ]);
    }

    public function create()
    {
        return view('admin.pick-list-template.edit', [
            'itemSuggestions' => $this->itemSuggestions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $template = new PickListTemplate();
        $template->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        $template->save();

        $this->syncItems($template, $validated['items'] ?? []);

        session()->flash('message', 'Pick list template has been created');
        session()->flash('message-title', 'Template created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.pick-list-template.index');
    }

    public function edit(PickListTemplate $pickListTemplate)
    {
        $pickListTemplate->load('items');

        return view('admin.pick-list-template.edit', [
            'template' => $pickListTemplate,
            'itemSuggestions' => $this->itemSuggestions(),
        ]);
    }

    public function update(Request $request, PickListTemplate $pickListTemplate): RedirectResponse
    {
        $validated = $this->validateRequest($request);

        $pickListTemplate->fill([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);
        $pickListTemplate->save();

        $this->syncItems($pickListTemplate, $validated['items'] ?? []);

        session()->flash('message', 'Pick list template has been updated');
        session()->flash('message-title', 'Template updated');
        session()->flash('message-type', 'success');

        return redirect()->back();
    }

    public function destroy(PickListTemplate $pickListTemplate): RedirectResponse
    {
        $pickListTemplate->delete();

        session()->flash('message', 'Pick list template has been deleted');
        session()->flash('message-title', 'Template deleted');
        session()->flash('message-type', 'danger');

        return redirect()->route('admin.pick-list-template.index');
    }

    public function duplicate(PickListTemplate $pickListTemplate): RedirectResponse
    {
        $pickListTemplate->load('items');

        $copy = new PickListTemplate();
        $copy->name = trim((string) $pickListTemplate->name).' (Copy)';
        $copy->description = $pickListTemplate->description;
        $copy->save();

        foreach ($pickListTemplate->items as $item) {
            $copy->items()->create([
                'item_name' => (string) $item->item_name,
                'quantity_type' => (string) $item->quantity_type,
                'quantity_value' => (int) $item->quantity_value,
                'sort_order' => (int) ($item->sort_order ?? 0),
            ]);
        }

        session()->flash('message', 'Pick list template has been duplicated');
        session()->flash('message-title', 'Template duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.pick-list-template.edit', $copy);
    }

    private function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.item_name' => ['required', 'string', 'max:255'],
            'items.*.quantity_type' => ['required', 'string', 'in:'.implode(',', PickListTemplateItem::TYPES)],
            'items.*.quantity_value' => ['required', 'integer', 'min:1'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['items'] = collect($validated['items'] ?? [])
            ->map(function (array $row): array {
                return [
                    'item_name' => trim((string) ($row['item_name'] ?? '')),
                    'quantity_type' => (string) ($row['quantity_type'] ?? PickListTemplateItem::TYPE_PER_PARTICIPANT),
                    'quantity_value' => max(1, (int) ($row['quantity_value'] ?? 1)),
                    'sort_order' => max(0, (int) ($row['sort_order'] ?? 0)),
                ];
            })
            ->filter(fn (array $row): bool => $row['item_name'] !== '')
            ->values()
            ->all();

        return $validated;
    }

    private function syncItems(PickListTemplate $template, array $items): void
    {
        $template->items()->delete();

        foreach ($items as $index => $row) {
            $template->items()->create([
                'item_name' => $row['item_name'],
                'quantity_type' => $row['quantity_type'],
                'quantity_value' => $row['quantity_value'],
                'sort_order' => $row['sort_order'] ?: (($index + 1) * 10),
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function itemSuggestions(): array
    {
        return PickListTemplateItem::query()
            ->whereRaw("TRIM(item_name) <> ''")
            ->select('item_name')
            ->distinct()
            ->orderBy('item_name')
            ->pluck('item_name')
            ->map(fn ($value) => trim((string) $value))
            ->filter(fn (string $value) => $value !== '')
            ->values()
            ->all();
    }
}
