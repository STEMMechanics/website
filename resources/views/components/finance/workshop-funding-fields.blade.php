@props(['namePrefix' => null, 'model' => 'item', 'billingLocked' => false])
@php
    $workshopCatalog = app(\App\Support\RequestMemo::class)->remember('workshop-funding-catalog', fn () => app(\App\Services\Finance\WorkshopFunding::class)->catalog());
@endphp
<div x-id="['funding-options']" x-data="SM.workshopFundingEditor({{ $model }}, @js($workshopCatalog), @js($billingLocked))" x-on:keydown.escape.window="open = false" x-on:resize.window="open = false" x-on:scroll.window="if (open) position($refs.workshopTrigger, menuWidth)">
    <div class="relative flex items-center">
        <i x-show="linkedWorkshop" class="fa-solid fa-link pointer-events-none absolute left-3 text-xs text-sky-600" x-bind:title="linkedWorkshop?.label" aria-hidden="true"></i>
        <x-ui.input-control class="h-11 pr-10!" x-bind:class="linkedWorkshop ? 'pl-8!' : ''" aria-label="Workshop description" maxlength="500" :readonly="$billingLocked" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="open" x-bind:aria-controls="$id('funding-options')" x-bind:aria-activedescendant="open &amp;&amp; matches.length ? $id('funding-options') + '-' + selected : null" placeholder="Workshop name" x-model="item.description" x-on:input="editDescription($el); $dispatch('workshop-line-changed')" x-on:keydown.arrow-down.prevent.stop="if (!open) browse($el); else move(1)" x-on:keydown.arrow-up.prevent.stop="move(-1)" x-on:keydown.enter="if (open &amp;&amp; matches[selected]) { $event.preventDefault(); $event.stopPropagation(); choose(matches[selected]); $dispatch('workshop-line-changed'); }" />
        <x-ui.button type="button" variant="plain" class="absolute right-0 size-11 p-0! text-slate-500" x-ref="workshopTrigger" aria-label="Workshop link options" x-bind:title="linkedWorkshop ? 'Linked to ' + linkedWorkshop.label : 'Link a workshop'" x-bind:aria-expanded="open" x-on:click="browse($el)"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></x-ui.button>
    </div>
    <template x-teleport="body" x-on:workshop-line-changed="">
        <div x-cloak x-show="open" x-on:click.outside="open = false" class="fixed z-50 rounded-xl border border-slate-200 bg-white p-2 shadow-xl" x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px' }">
            <x-ui.input-control aria-label="Find a workshop to link" placeholder="Search name, date or location" x-model="query" x-on:input="selected = 0" x-on:keydown.arrow-down.prevent.stop="move(1)" x-on:keydown.arrow-up.prevent.stop="move(-1)" x-on:keydown.enter.prevent.stop="if (matches[selected]) { choose(matches[selected]); $dispatch('workshop-line-changed'); }" />
            <div role="listbox" x-bind:id="$id('funding-options')" class="mt-1 max-h-48 overflow-y-auto">
                <template x-for="(option, optionIndex) in matches" :key="option.id">
                    <button type="button" role="option" x-bind:id="$id('funding-options') + '-' + optionIndex" x-bind:aria-selected="selected === optionIndex" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-sky-50" x-bind:class="selected === optionIndex ? 'bg-sky-50' : ''" x-on:click="choose(option); $dispatch('workshop-line-changed')" x-text="option.label"></button>
                </template>
                <p x-show="!matches.length" class="px-3 py-2 text-sm text-slate-500">No matches. You can keep a manual workshop name.</p>
            </div>
            <button type="button" x-show="linkedWorkshop" class="mt-1 flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-left text-sm text-slate-600" x-on:click="choose(null); $dispatch('workshop-line-changed')"><i class="fa-solid fa-link-slash" aria-hidden="true"></i>Remove link</button>
        </div>
    </template>
    @if($namePrefix)
        @foreach(['linked_workshop_id', 'allocation_basis', 'allocation_seats'] as $field)
            <input type="hidden" form="invoice-edit-form" name="{{ $namePrefix }}[{{ $field }}]" x-bind:value="item.details_json.workshop.{{ $field }} ?? ''">
        @endforeach
    @endif
</div>
