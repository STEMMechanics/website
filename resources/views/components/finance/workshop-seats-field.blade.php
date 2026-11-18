@props(['model' => 'item', 'billingLocked' => false])
@php
    $workshopCatalog = app(\App\Support\RequestMemo::class)->remember('workshop-funding-catalog', fn () => app(\App\Services\Finance\WorkshopFunding::class)->catalog());
@endphp
<div x-data="$SM.workshopFundingEditor({{ $model }}, @js($workshopCatalog), @js($billingLocked))" x-init="syncLiveSeatValue()" x-effect="syncAllocationInputs()" x-on:keydown.escape.window="menuOpen = false" x-on:resize.window="menuOpen = false" x-on:scroll.window="positionSeatsIfOpen()">
    <div class="relative">
        <x-ui.input-control class="h-11 pr-9!" aria-label="Workshop seats" type="number" min="0" max="10000" step="1" x-model="seatValue" x-bind:title="basisLabel" x-on:input="$dispatch('workshop-line-changed')" />
        <x-ui.button type="button" variant="plain" class="absolute right-0 top-0 h-11 w-8 p-0! text-slate-500" x-ref="seatsTrigger" aria-label="Seats options" x-bind:aria-expanded="menuOpen" x-on:click="toggleSeatsMenu($el)"><i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i></x-ui.button>
    </div>
    <template x-teleport="body">
        <div x-cloak x-show="menuOpen" x-on:click.outside="menuOpen = false" class="fixed z-50 rounded-xl border border-slate-200 bg-white p-2 shadow-xl" x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px', width: menuWidth + 'px' }">
            <template x-for="choice in [{ value: 'manual', label: 'Manual number' }, { value: 'capacity', label: 'Workshop capacity' }, { value: 'tickets', label: 'Registered tickets' }, { value: 'attendance', label: 'Attendance count' }]" :key="choice.value">
                <x-ui.button variant="plain" type="button" class="flex w-full items-center justify-between gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-sky-50 disabled:opacity-40" x-bind:disabled="choice.value !== 'manual' &amp;&amp; !linkedWorkshop" x-on:click="setSeatsAndDispatch(choice.value)"><span x-text="choice.label"></span><i x-show="item.details_json.workshop.allocation_basis === choice.value" class="fa-solid fa-check text-sky-600" aria-hidden="true"></i></x-ui.button>
            </template>
            <p x-show="!linkedWorkshop" class="px-3 py-2 text-xs text-slate-500">Link a workshop to use its capacity or tickets.</p>
        </div>
    </template>
</div>
