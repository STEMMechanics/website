@props([
    'revertAction',
    'amendAction',
    'revertDisabled' => 'false',
    'amendDisabled' => 'false',
    'itemLabel' => 'items',
])

<div class="flex w-full min-w-0 flex-col items-stretch gap-1.5 sm:w-auto sm:flex-row sm:items-center">
    <span class="w-full cursor-default sm:w-auto" x-on:click.stop.prevent>
        <x-ui.button
            type="button"
            color="secondary"
            size="small"
            class="w-full gap-1 px-2 py-1 font-medium text-primary-color shadow-sm hover:border-sky-300 hover:bg-sky-50 hover:text-primary-color-dark sm:w-auto"
            x-bind:disabled="{{ $revertDisabled }}"
            x-on:click.stop.prevent="{{ $revertAction }}"
        ><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Revert to blueprint</span></x-ui.button>
    </span>
    <span class="w-full cursor-default sm:w-auto" x-on:click.stop.prevent>
        <x-ui.button
            type="button"
            color="secondary"
            size="small"
            class="w-full gap-1 px-2 py-1 font-medium text-primary-color shadow-sm hover:border-sky-300 hover:bg-sky-50 hover:text-primary-color-dark sm:w-auto"
            x-bind:disabled="{{ $amendDisabled }}"
            x-on:click.stop.prevent="{{ $amendAction }}"
        ><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Amend missing blueprint {{ $itemLabel }}</span></x-ui.button>
    </span>
</div>
