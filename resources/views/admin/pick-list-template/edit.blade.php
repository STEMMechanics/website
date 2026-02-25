@php
    $editing = isset($template);
    $seedItems = old('items');

    if (! is_array($seedItems)) {
        $seedItems = $editing
            ? $template->items->map(fn ($item) => [
                'item_name' => (string) $item->item_name,
                'quantity_type' => (string) $item->quantity_type,
                'quantity_value' => (int) $item->quantity_value,
                'sort_order' => (int) ($item->sort_order ?? 0),
            ])->values()->all()
            : [];
    }
@endphp

<x-layout>
    <x-mast backRoute="admin.pick-list-template.index" backTitle="Pick List Templates">{{ $editing ? 'Edit' : 'Create' }} Pick List Template</x-mast>

    <x-container class="mt-4">
        <form method="POST" action="{{ route('admin.pick-list-template.'.($editing ? 'update' : 'store'), $template ?? []) }}" x-data="{
            items: @js($seedItems),
            newBlankItem() {
                return {
                    item_name: '',
                    quantity_type: 'per_participant',
                    quantity_value: 1,
                    sort_order: 0,
                };
            },
            isBlankItem(item) {
                const name = String(item?.item_name || '').trim();
                return name === '';
            },
            hasSingleTrailingBlank() {
                if (this.items.length === 0) {
                    return false;
                }
                const blankCount = this.items.filter((item) => this.isBlankItem(item)).length;
                return blankCount === 1 && this.isBlankItem(this.items[this.items.length - 1]);
            },
            ensureSingleTrailingBlank() {
                const nonBlank = this.items.filter((item) => !this.isBlankItem(item));
                this.items = [...nonBlank, this.newBlankItem()];
                this.normalizeSort();
            },
            handleRowChange(index) {
                const isLastRow = index === (this.items.length - 1);
                if (isLastRow && !this.isBlankItem(this.items[index])) {
                    this.items.push(this.newBlankItem());
                    this.normalizeSort();
                    return;
                }

                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
            normalizeSort() {
                this.items = this.items.map((item, index) => ({
                    ...item,
                    sort_order: (index + 1) * 10,
                }));
            },
            addItem() {
                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
            removeItem(index) {
                this.items.splice(index, 1);
                this.ensureSingleTrailingBlank();
            },
            moveUp(index) {
                if (index <= 0) {
                    return;
                }
                const previous = this.items[index - 1];
                this.items[index - 1] = this.items[index];
                this.items[index] = previous;
                this.normalizeSort();
                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
            moveDown(index) {
                const lastRealIndex = this.items.length - 2;
                if (index >= lastRealIndex) {
                    return;
                }
                const next = this.items[index + 1];
                this.items[index + 1] = this.items[index];
                this.items[index] = next;
                this.normalizeSort();
                if (!this.hasSingleTrailingBlank()) {
                    this.ensureSingleTrailingBlank();
                }
            },
        }" x-init="ensureSingleTrailingBlank()">
            @csrf
            @if($editing)
                @method('PUT')
            @endif

            <div class="mb-4">
                <x-ui.input label="Template Name" name="name" value="{{ old('name', $template->name ?? '') }}" />
            </div>

            <div class="mb-6">
                <x-ui.input type="textarea" label="Template Notes" name="description" value="{{ old('description', $template->description ?? '') }}" rows="2" class="mb-0" />
            </div>

            <div class="rounded-lg border border-gray-200 p-4 mb-6">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">Items</h2>
                    <x-ui.button type="button" x-on:click="addItem()">Add Item</x-ui.button>
                </div>

                <template x-if="items.length === 0">
                    <p class="text-sm text-gray-600">No items yet. Add your first pick list item.</p>
                </template>

                <div x-show="items.length > 0">
                    <table class="min-w-full border border-gray-200 rounded-md">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2 border-b">Item</th>
                                <th class="text-left p-2 border-b">Type</th>
                                <th class="text-left p-2 border-b">Quantity</th>
                                <th class="text-left p-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-b last:border-b-0">
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-model="item.item_name" x-bind:name="!isBlankItem(item) ? `items[${index}][item_name]` : null">
                                        <x-ui.input
                                            name="item_name_placeholder"
                                            label="Item"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            :suggestions="$itemSuggestions ?? []"
                                            x-model="item.item_name"
                                            x-on:input="item.item_name = $event.target.value; handleRowChange(index)"
                                            x-on:change="item.item_name = $event.target.value; handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.select
                                            name="quantity_type_placeholder"
                                            label="Type"
                                            :noLabel="true"
                                            class="mb-0"
                                            x-model="item.quantity_type"
                                            x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_type]` : null"
                                            x-on:change="item.quantity_type = $event.target.value; handleRowChange(index)">
                                            <option value="per_participant">Per Participant</option>
                                            <option value="fixed">Fixed amount</option>
                                        </x-ui.select>
                                    </td>
                                    <td class="p-2 align-top">
                                        <x-ui.input
                                            type="number"
                                            name="quantity_value_placeholder"
                                            label="Quantity"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0 max-w-28"
                                            min="1"
                                            step="1"
                                            x-model="item.quantity_value"
                                            x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_value]` : null"
                                            x-bind:required="!isBlankItem(item)"
                                            x-on:input="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)"
                                            x-on:change="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-model="item.sort_order" :name="!isBlankItem(item) ? `items[${index}][sort_order]` : null">
                                        <div class="flex items-center gap-3">
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveUp(index)" :disabled="index === 0 || isBlankItem(item)" title="Move up">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </button>
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveDown(index)" :disabled="index >= (items.length - 2) || isBlankItem(item)" title="Move down">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeItem(index)" title="Remove">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <x-ui.button type="link" color="outline" href="{{ route('admin.pick-list-template.index') }}">Cancel</x-ui.button>
                <x-ui.button type="submit">{{ $editing ? 'Save Template' : 'Create Template' }}</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
