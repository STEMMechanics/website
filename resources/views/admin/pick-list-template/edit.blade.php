@php
    $editing = isset($template);
    $seedItems = old('items');

    if (! is_array($seedItems)) {
        $seedItems = $editing
            ? $template->items->map(fn ($item) => [
                'id' => (int) $item->id,
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
            submitting: false,
            seededBlankItem(previousItem = null) {
                const previousType = String(previousItem?.quantity_type ?? '');
                const previousValue = Number.parseInt(String(previousItem?.quantity_value ?? 1), 10) || 1;

                return {
                    id: null,
                    item_name: '',
                    quantity_type: ['per_participant', 'fixed'].includes(previousType) ? previousType : 'per_participant',
                    quantity_value: Math.max(1, previousValue),
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
                const previousItem = nonBlank.length > 0 ? nonBlank[nonBlank.length - 1] : null;
                this.items = [...nonBlank, this.seededBlankItem(previousItem)];
                this.normalizeSort();
            },
            handleRowChange(index) {
                const isLastRow = index === (this.items.length - 1);
                if (isLastRow && !this.isBlankItem(this.items[index])) {
                    this.items.push(this.seededBlankItem(this.items[index]));
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
        }" x-init="ensureSingleTrailingBlank()" x-on:submit="submitting = true">
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

            <div class="rounded-lg border border-gray-200 p-4 mb-6 overflow-x-auto overflow-y-visible">
                <div class="flex items-center justify-between mb-3">
                    <h2 class="text-lg font-semibold">Items</h2>
                </div>

                <template x-if="items.length === 0">
                    <p class="text-sm text-gray-600">No items yet. Add your first pick list item.</p>
                </template>

                <div x-show="items.length > 0">
                    <table class="min-w-full border border-gray-200 rounded-md">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="text-left p-2 border-b">Item</th>
                                <th class="text-left p-2 border-b hidden md:table-cell">Type</th>
                                <th class="text-left p-2 border-b hidden md:table-cell">Quantity</th>
                                <th class="text-left p-2 border-b">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="(item, index) in items" :key="index">
                                <tr class="border-b last:border-b-0">
                                    <td class="p-2 align-top">
                                        <input type="hidden" x-model="item.id" :name="!isBlankItem(item) && item.id ? `items[${index}][id]` : null">
                                        <input type="hidden" x-model="item.sort_order" :name="!isBlankItem(item) ? `items[${index}][sort_order]` : null">
                                        <input type="hidden" x-model="item.item_name" x-bind:name="!isBlankItem(item) ? `items[${index}][item_name]` : null">
                                        <input type="hidden" x-model="item.quantity_type" x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_type]` : null">
                                        <input type="hidden" x-model="item.quantity_value" x-bind:name="!isBlankItem(item) ? `items[${index}][quantity_value]` : null">

                                        <div class="md:hidden grid grid-cols-1 gap-2">
                                            <div>
                                                <label class="block text-xs font-semibold text-gray-600 mb-1 md:hidden">Item</label>
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
                                            </div>
                                            <div class="grid grid-cols-2 gap-2">
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1 md:hidden">Type</label>
                                                    <x-ui.select
                                                        name="quantity_type_placeholder"
                                                        label="Type"
                                                        :noLabel="true"
                                                        class="mb-0"
                                                        x-model="item.quantity_type"
                                                        x-on:change="item.quantity_type = $event.target.value; handleRowChange(index)">
                                                        <option value="per_participant">Per Participant</option>
                                                        <option value="fixed">Fixed amount</option>
                                                    </x-ui.select>
                                                </div>
                                                <div>
                                                    <label class="block text-xs font-semibold text-gray-600 mb-1 md:hidden">Quantity</label>
                                                    <x-ui.input
                                                        type="number"
                                                        name="quantity_value_placeholder"
                                                        label="Quantity"
                                                        :noLabel="true"
                                                        class="mb-0"
                                                        fieldClasses="mt-0"
                                                        min="1"
                                                        step="1"
                                                        x-model="item.quantity_value"
                                                        x-bind:required="!isBlankItem(item)"
                                                        x-on:input="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)"
                                                        x-on:change="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)" />
                                                </div>
                                            </div>
                                        </div>

                                        <div class="hidden md:block">
                                            <x-ui.input
                                                name="item_name_placeholder_desktop"
                                                label="Item"
                                                :noLabel="true"
                                                class="mb-0"
                                                fieldClasses="mt-0"
                                                :suggestions="$itemSuggestions ?? []"
                                                x-model="item.item_name"
                                                x-on:input="item.item_name = $event.target.value; handleRowChange(index)"
                                                x-on:change="item.item_name = $event.target.value; handleRowChange(index)" />
                                        </div>
                                    </td>
                                    <td class="p-2 align-top hidden md:table-cell">
                                        <x-ui.select
                                            name="quantity_type_placeholder_desktop"
                                            label="Type"
                                            :noLabel="true"
                                            class="mx-0"
                                            x-model="item.quantity_type"
                                            x-on:change="item.quantity_type = $event.target.value; handleRowChange(index)">
                                            <option value="per_participant">Per Participant</option>
                                            <option value="fixed">Fixed amount</option>
                                        </x-ui.select>
                                    </td>
                                    <td class="p-2 align-top hidden md:table-cell">
                                        <x-ui.input
                                            type="number"
                                            name="quantity_value_placeholder_desktop"
                                            label="Quantity"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            min="1"
                                            step="1"
                                            x-model="item.quantity_value"
                                            x-bind:required="!isBlankItem(item)"
                                            x-on:input="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)"
                                            x-on:change="item.quantity_value = Number($event.target.value || 1); handleRowChange(index)" />
                                    </td>
                                    <td class="p-2 align-middle">
                                        <div class="flex items-center justify-center gap-3 h-full">
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
                <x-ui.button type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting">{{ $editing ? 'Save Template' : 'Create Template' }}</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span>{{ $editing ? 'Saving...' : 'Creating...' }}</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
