@props(['namePrefix' => null, 'model' => 'item', 'billingLocked' => false, 'allowSpacing' => false, 'travelRow' => false, 'allowLocations' => false])
@php
    $workshopCatalog = app(\App\Support\RequestMemo::class)->remember('workshop-funding-catalog', fn () => app(\App\Services\Finance\WorkshopFunding::class)->catalog());
    $locationCatalog = ($travelRow || $allowLocations) && ! $billingLocked
        ? app(\App\Support\RequestMemo::class)->remember('travel-location-catalog', fn () => \App\Models\Location::query()->orderBy('name')->get(['id', 'name', 'default_travel_hours'])->reject(fn ($location) => $location->isOnline())->values()->map(fn ($location) => [
            'id' => (string) $location->id,
            'type' => 'location',
            'location_id' => (string) $location->id,
            'label' => (string) $location->name,
            'name' => (string) $location->name,
            'default_travel_hours' => $location->default_travel_hours,
        ])->all())
        : [];
@endphp
<div x-id="['funding-options']" x-data="$SM.workshopFundingEditor({{ $model }}, @js($workshopCatalog), @js($billingLocked), {{ $allowSpacing ? 'true' : 'false' }}, {{ $travelRow ? 'true' : 'false' }}, @js($locationCatalog), {{ $allowLocations ? 'true' : 'false' }})" x-on:keydown.escape.window="open = false" x-on:keydown.tab.window="open = false" x-on:resize.window="positionDescriptionIfOpen()" x-on:scroll.window="positionDescriptionIfOpen()">
    <div class="relative flex items-center">
        <i x-show="linkedOption" class="fa-solid fa-link pointer-events-none absolute left-3 text-xs text-sky-600" x-bind:title="$SM.linkedOptionLabel(linkedOption)" aria-hidden="true"></i>
        <x-ui.input-control class="h-11 pr-10!" x-bind:class="linkedOption ? 'pl-8!' : ''" x-ref="workshopDescription" :aria-label="($travelRow || $allowLocations) ? 'Workshop or location' : ($billingLocked ? 'Find a workshop to link' : 'Workshop description')" maxlength="500" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="open" x-bind:aria-controls="$id('funding-options')" x-bind:aria-activedescendant="open &amp;&amp; matches.length ? $id('funding-options') + '-' + selected : null" placeholder="{{ ($travelRow || $allowLocations) ? 'Workshop or location' : 'Workshop name' }}" x-model="descriptionValue" x-on:input="updateDescriptionAndDispatch($el)" x-on:keydown.arrow-down.prevent.stop="handleArrowDown($el)" x-on:keydown.arrow-up.prevent.stop="move(-1)" x-on:keydown.enter="handleEnter($event)" />
        <x-ui.button type="button" variant="plain" class="absolute right-0 text-slate-500" x-ref="workshopTrigger" :aria-label="$travelRow ? 'Workshop or location options' : ($allowLocations ? 'Workshop or location options' : 'Workshop link options')" x-bind:title="linkedOption ? 'Linked to ' + linkedOption.label : '{{ ($travelRow || $allowLocations) ? 'Find a workshop or location' : 'Link a workshop' }}'" x-bind:aria-expanded="open" x-on:click="browse($el)" size="icon"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></x-ui.button>
    </div>
    <template x-teleport="body" x-on:workshop-line-changed="">
        <div x-cloak x-show="open && matches.length > 0" x-on:click.outside="open = false" x-on:keydown.tab="closeAndFocusDescription()" class="fixed z-50 rounded-xl border border-slate-200 bg-white p-2 shadow-xl" x-bind:style="{ top: menuAbove ? 'auto' : menuTop + 'px', bottom: menuAbove ? menuBottom + 'px' : 'auto', left: menuLeft + 'px', width: menuWidth + 'px' }">
            <div role="listbox" x-bind:id="$id('funding-options')" class="max-h-48 overflow-y-auto" x-bind:style="{ maxHeight: menuListMaxHeight + 'px' }">
                <template x-for="(option, optionIndex) in matches" :key="option.id">
                    <x-ui.button variant="plain" type="button" role="option" x-bind:id="$id('funding-options') + '-' + optionIndex" x-bind:aria-selected="selected === optionIndex" class="block w-full rounded px-3 py-2 text-left text-sm hover:bg-sky-50" x-bind:class="selected === optionIndex ? 'bg-sky-50' : ''" x-on:click="chooseAndDispatch(option)" x-text="option.label"></x-ui.button>
                </template>
            </div>
            <x-ui.button variant="plain" type="button" x-show="linkedOption" class="mt-1 flex w-full items-center gap-2 border-t border-slate-100 px-3 py-2 text-left text-sm text-slate-600" x-on:click="chooseAndDispatch(null)"><i class="fa-solid fa-link-slash" aria-hidden="true"></i>Remove link</x-ui.button>
        </div>
    </template>
    @if($namePrefix)
        @foreach(['linked_workshop_id', 'allocation_basis', 'allocation_seats'] as $field)
            <input type="hidden" form="invoice-edit-form" name="{{ $namePrefix }}[{{ $field }}]" x-bind:value="item.details_json.workshop.{{ $field }} ?? ''">
        @endforeach
    @endif
</div>
