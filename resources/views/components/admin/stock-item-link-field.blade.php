@props([
    'model' => 'item',
    'stockItems' => [],
    'stockItemsExpression' => null,
    'placeholder' => 'Item name',
    'ariaLabel' => 'Item name or stock item',
    'label' => null,
    'noMatchesText' => 'No matches. You can keep a manual item name.',
    'allowLinkedItemTextEdit' => false,
])

@php
    $usesStockItemsExpression = is_string($stockItemsExpression) && trim($stockItemsExpression) !== '';
    $catalog = $usesStockItemsExpression
        ? []
        : collect($stockItems)
            ->map(function ($stockItem) {
                return [
                    'id' => (int) data_get($stockItem, 'id'),
                    'name' => $stockItem instanceof \App\Models\StockItem
                        ? $stockItem->linkLabel()
                        : (string) data_get($stockItem, 'name'),
                    'sku' => (string) data_get($stockItem, 'sku', ''),
                    'status' => (string) data_get($stockItem, 'status', 'active'),
                    'is_kit' => (bool) data_get($stockItem, 'is_kit', false),
                    'group_name' => (string) data_get($stockItem, 'group.name', ''),
                    'variant_name' => (string) data_get($stockItem, 'variant_name', ''),
                ];
            })
            ->filter(fn (array $stockItem): bool => $stockItem['id'] > 0 && $stockItem['name'] !== '')
            ->values()
            ->all();
@endphp

<div
    x-id="['stock-item-options', 'stock-item-input']"
    @if($usesStockItemsExpression)
        x-data="SM.stockItemLinkEditor({{ $model }}, {{ $stockItemsExpression }}, @js((bool) $allowLinkedItemTextEdit))"
    @else
        x-data="SM.stockItemLinkEditor({{ $model }}, @js($catalog), @js((bool) $allowLinkedItemTextEdit))"
    @endif
    x-init="if (linkedStockItem && !model.item_name) model.item_name = linkedStockItem.name"
    x-on:keydown.escape.window="open = false"
    x-on:resize.window="if (open) position($refs.stockItemTrigger, menuWidth)"
    x-on:scroll.window="if (open) position($refs.stockItemTrigger, menuWidth)"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    @if(is_string($label) && trim($label) !== '')
        <label class="mb-1 block pl-1 text-sm" x-bind:for="$id('stock-item-input')">{{ $label }}</label>
    @endif
    <div class="relative flex items-center">
        <i
            x-show="linkedStockItem"
            class="fa-solid fa-link pointer-events-none absolute left-3 text-xs text-sky-600"
            x-bind:title="linkedStockItem?.name"
            aria-hidden="true"
        ></i>
        <x-ui.input-control
            x-ref="stockItemInput"
            x-bind:id="$id('stock-item-input')"
            class="h-11 pr-10!"
            x-bind:class="linkedStockItem ? 'pl-8!' : ''"
            aria-label="{{ $ariaLabel }}"
            placeholder="{{ $placeholder }}"
            maxlength="255"
            autocomplete="off"
            x-model="model.item_name"
            x-bind:readonly="!allowLinkedItemTextEdit && Boolean(model.stock_item_id)"
            role="combobox"
            aria-autocomplete="list"
            x-bind:aria-expanded="open"
            x-bind:aria-controls="$id('stock-item-options')"
            x-bind:aria-activedescendant="open && matches.length ? $id('stock-item-options') + '-' + selected : null"
            x-on:input="editDescription($el)"
            x-on:keydown.arrow-down.prevent.stop="if (!open) browse($el); else move(1)"
            x-on:keydown.arrow-up.prevent.stop="move(-1)"
            x-on:keydown.enter="handleEnter($event)"
        />
        <x-ui.button
            type="button"
            variant="plain"
            class="absolute right-0 size-11 p-0! text-slate-500"
            x-ref="stockItemTrigger"
            aria-label="Stock item link options"
            x-bind:title="linkedStockItem ? 'Linked to ' + linkedStockItem.name : 'Link a stock item'"
            x-bind:aria-expanded="open"
            x-on:click="browse($el)"
        >
            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
        </x-ui.button>
    </div>

    <template x-teleport="body">
        <div
            x-cloak
            x-show="open"
            x-on:click.outside="open = false"
            class="fixed z-50 rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
            x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px' }"
        >
            <x-ui.input-control
                aria-label="Find a stock item to link"
                placeholder="Search name or SKU"
                x-model="query"
                x-on:input="selected = 0"
                x-on:keydown.arrow-down.prevent.stop="move(1)"
                x-on:keydown.arrow-up.prevent.stop="move(-1)"
                x-on:keydown.enter="handleEnter($event)"
            />
            <div
                class="mt-2 max-h-64 overflow-y-auto"
                role="listbox"
                x-bind:id="$id('stock-item-options')"
            >
                <template x-for="(option, optionIndex) in matches" :key="option.id">
                    <button
                        type="button"
                        role="option"
                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm"
                        x-bind:class="optionIndex === selected ? 'bg-sky-50 text-sky-800' : 'text-slate-700 hover:bg-slate-50'"
                        x-bind:id="$id('stock-item-options') + '-' + optionIndex"
                        x-bind:aria-selected="optionIndex === selected"
                        x-on:mouseenter="selected = optionIndex"
                        x-on:click="choose(option)"
                    >
                            <span class="min-w-0 truncate" x-text="option.name"></span>
                            <span class="flex shrink-0 items-center gap-2">
                                <span x-show="option.is_kit" class="rounded-full bg-sky-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-sky-800">Recipe</span>
                                <span x-show="option.status === 'archived'" class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">Archived</span>
                                <span class="text-xs text-slate-400" x-show="option.sku" x-text="option.sku"></span>
                        </span>
                    </button>
                </template>
                <p x-show="!matches.length" class="px-3 py-2 text-sm text-slate-500">{{ $noMatchesText }}</p>
            </div>
            <button
                type="button"
                class="mt-2 w-full rounded-lg border-t border-slate-100 px-3 pt-2 text-left text-xs font-semibold text-slate-600 hover:text-slate-900"
                x-show="model.stock_item_id"
                x-on:click="choose(null)"
            >
                Remove stock item link
            </button>
        </div>
    </template>
</div>
