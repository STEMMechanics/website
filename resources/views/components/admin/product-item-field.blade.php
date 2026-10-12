@props([
    'model' => 'item',
    'catalog' => [],
    'catalogExpression' => null,
    'placeholder' => 'Item name',
    'ariaLabel' => 'Item name or product',
    'label' => null,
    'noMatchesText' => 'No matches. You can keep a manual item name.',
    'requireSelection' => false,
    'disabledExpression' => 'false',
    'itemNameSuggestions' => [],
    'itemNameSuggestionsExpression' => null,
])

@php
    $usesCatalogExpression = is_string($catalogExpression) && trim($catalogExpression) !== '';
    $usesItemNameSuggestionsExpression = is_string($itemNameSuggestionsExpression) && trim($itemNameSuggestionsExpression) !== '';
    $selectionExpression = $requireSelection ? 'true' : 'false';
@endphp

<div
    x-id="['product-item-options', 'product-item-input', 'product-item-help']"
    @if($usesCatalogExpression)
        @if($usesItemNameSuggestionsExpression)
            x-data="SM.productItemLinkEditor({{ $model }}, {{ $catalogExpression }}, {{ $itemNameSuggestionsExpression }}, {{ $selectionExpression }})"
        @else
            x-data="SM.productItemLinkEditor({{ $model }}, {{ $catalogExpression }}, @js($itemNameSuggestions), {{ $selectionExpression }})"
        @endif
    @else
        x-data="SM.productItemLinkEditor({{ $model }}, @js($catalog), @js($itemNameSuggestions), {{ $selectionExpression }})"
    @endif
    x-init="if (linkedProduct && !model.item_name) model.item_name = linkedProduct.name"
    x-effect="model = {{ $model }}"
    x-on:keydown.escape.window="close()"
    x-on:resize.window="if (open) position($refs.productItemInput, menuWidth)"
    x-on:scroll.window="if (open) position($refs.productItemInput, menuWidth)"
    {{ $attributes->merge(['class' => 'relative']) }}
>
    @if(is_string($label) && trim($label) !== '')
        <label class="mb-1 block pl-1 text-sm" x-bind:for="$id('product-item-input')">{{ $label }}</label>
    @endif
    <div class="relative flex items-center">
        <x-ui.button
            variant="plain"
            size="icon-small"
            icon="link"
            label="Open linked item in a new tab"
            x-show="linkedProduct"
            class="absolute left-1 z-10 rounded-md text-xs text-sky-600 hover:text-sky-800"
            x-bind:href="linkedProduct?.url"
            x-bind:disabled="!linkedProduct?.url"
            x-bind:title="'Open ' + (linkedProduct?.name || 'linked item') + ' in a new tab'"
            x-bind:aria-label="'Open ' + (linkedProduct?.name || 'linked item') + ' in a new tab'"
            x-on:click="close()"
            target="_blank"
        />
        <x-ui.input-control
            x-ref="productItemInput"
            x-bind:disabled="{{ $disabledExpression }}"
            x-bind:id="$id('product-item-input')"
            class="h-11 pr-10!"
            x-bind:class="linkedProduct ? 'pl-10!' : ''"
            aria-label="{{ $ariaLabel }}"
            placeholder="{{ $placeholder }}"
            maxlength="255"
            autocomplete="off"
            x-model="model.item_name"
            x-bind:required="requireSelection"
            x-bind:pattern="requireSelection && !model.product_id ? 'a^' : null"
            x-bind:title="requireSelection ? 'Choose an item from the suggestions' : null"
            role="combobox"
            aria-autocomplete="list"
            aria-haspopup="listbox"
            x-bind:aria-describedby="$id('product-item-help')"
            x-bind:aria-expanded="open"
            x-bind:aria-controls="$id('product-item-options')"
            x-bind:aria-activedescendant="open && matches[selected] ? $id('product-item-options') + '-' + selected : null"
            x-on:input="editDescription($el)"
            x-on:keydown.arrow-down="move($event, 1)"
            x-on:keydown.arrow-up="move($event, -1)"
            x-on:keydown.enter="handleEnter($event)"
            x-on:keydown.tab="close()"
        />
        <x-ui.button
            type="button"
            variant="plain"
            class="absolute right-0 text-slate-500"
            x-ref="productItemTrigger"
            x-bind:disabled="{{ $disabledExpression }}"
            aria-label="Product or material suggestions"
            x-bind:title="linkedProduct ? 'Linked to ' + linkedProduct.name : 'Link a product or material'"
            x-bind:aria-expanded="open"
            x-on:click="browse($el)" size="icon">
            <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
        </x-ui.button>
    </div>
    <span class="sr-only" x-bind:id="$id('product-item-help')">Use Up and Down to select a suggestion, then Enter to choose it. @if(!$requireSelection)Without a selection, Enter keeps your text. Command or Control plus Enter chooses the first suggestion. @endif Escape closes the suggestions.</span>

    <template x-teleport="body">
        <div
            x-ref="productItemOptions"
            x-cloak
            x-show="open"
            x-on:click.outside="close()"
            class="fixed z-50 overflow-y-auto rounded-xl border border-slate-200 bg-white p-2 shadow-xl"
            x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px', maxHeight: menuMaxHeight + 'px' }"
        >
            <div role="listbox" x-bind:id="$id('product-item-options')">
                <template x-for="(option, optionIndex) in matches" :key="option.id">
                    <x-ui.button variant="plain"
                        type="button"
                        role="option"
                        tabindex="-1"
                        class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm"
                        x-bind:class="optionIndex === selected ? 'bg-sky-50 text-sky-800' : 'text-slate-700 hover:bg-slate-50'"
                        x-bind:id="$id('product-item-options') + '-' + optionIndex"
                        x-bind:aria-selected="optionIndex === selected"
                        x-on:click="choose(option)"
                    >
                            <span class="min-w-0 truncate" x-text="option.name"></span>
                            <span class="flex shrink-0 items-center gap-2">
                                <x-ui.badge color="purple" uppercase class="px-2 py-0.5 text-[10px]" x-show="option.suggestionType === 'blueprint-text'">Blueprint Item</x-ui.badge>
                                <x-ui.badge color="sky" uppercase class="px-2 py-0.5 text-[10px]" x-show="option.suggestionType === 'product' && !option.is_kit && option.product_type !== 'material'">Store Item</x-ui.badge>
                                <x-ui.badge color="success" uppercase class="px-2 py-0.5 text-[10px]" x-show="option.product_type === 'material'">Material</x-ui.badge>
                                <x-ui.badge color="warning" uppercase class="px-2 py-0.5 text-[10px]" x-show="option.is_kit">Kit</x-ui.badge>
                                <x-ui.badge color="slate" uppercase class="px-2 py-0.5 text-[10px]" x-show="option.status === 'archived'">Archived</x-ui.badge>
                        </span>
                    </x-ui.button>
                </template>
                <p x-show="!matches.length" class="px-3 py-2 text-sm text-slate-500">{{ $noMatchesText }}</p>
            </div>
            <x-ui.button variant="plain"
                type="button"
                class="mt-2 w-full rounded-lg border-t border-slate-100 px-3 pt-2 text-left text-xs font-semibold text-slate-600 hover:text-slate-900"
                x-show="model.product_id"
                x-on:click="choose(null)"
            >
                Remove product link
            </x-ui.button>
        </div>
    </template>
</div>
