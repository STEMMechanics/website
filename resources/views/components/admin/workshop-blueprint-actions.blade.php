@props([
    'revertAction',
    'amendAction',
    'revertDisabled' => 'false',
    'amendDisabled' => 'false',
    'itemLabel' => 'items',
])

<div class="flex shrink-0 flex-wrap items-center gap-1.5">
    <span class="cursor-default" x-on:click.stop.prevent>
        <x-ui.button
            type="button"
            color="secondary"
            size="small"
            class="gap-1 px-2 py-1 font-medium text-primary-color shadow-sm hover:border-sky-300 hover:bg-sky-50 hover:text-primary-color-dark"
            x-bind:disabled="{{ $revertDisabled }}"
            x-on:click.stop.prevent="{{ $revertAction }}"
        ><i class="fa-solid fa-rotate-left" aria-hidden="true"></i><span>Revert to blueprint</span></x-ui.button>
    </span>
    <span class="cursor-default" x-on:click.stop.prevent>
        <x-ui.button
            type="button"
            color="secondary"
            size="small"
            class="gap-1 px-2 py-1 font-medium text-primary-color shadow-sm hover:border-sky-300 hover:bg-sky-50 hover:text-primary-color-dark"
            x-bind:disabled="{{ $amendDisabled }}"
            x-on:click.stop.prevent="{{ $amendAction }}"
        ><i class="fa-solid fa-plus" aria-hidden="true"></i><span>Amend missing blueprint {{ $itemLabel }}</span></x-ui.button>
    </span>
</div>
