@props(['insideDialog' => false])
<div data-line-type-picker class="relative min-w-0 flex-1" x-data="$SM.lineTypePicker()" x-on:keydown.escape.window="open = false" x-on:resize.window="open = false" x-on:scroll.window="open = false">
    <x-ui.button variant="plain" type="button" class="flex min-h-11 w-full items-center justify-between gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-left text-sm" aria-label="Item type" aria-haspopup="true" x-bind:aria-expanded="open"
        x-on:click="positionAndToggle($el)">
        <span class="flex items-center gap-2"><i class="fa-solid text-slate-500" x-bind:class="itemTypeIcon(item.kind)" aria-hidden="true"></i><span x-text="itemTypeLabel(item.kind)"></span></span>
        <i class="fa-solid fa-chevron-down text-slate-400" aria-hidden="true"></i>
    </x-ui.button>
    @if($insideDialog)
        <div x-show="open" x-cloak x-on:click.outside="open = false" class="fixed z-50 max-h-80 w-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-xl" x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px' }">
            <template x-for="option in itemTypeOptions" :key="option.value">
                <x-ui.button variant="plain" type="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-sky-50" x-on:click="choose($el, index, option.value)">
                    <i class="fa-solid w-4 text-slate-500" x-bind:class="option.icon" aria-hidden="true"></i><span x-text="option.label"></span>
                </x-ui.button>
            </template>
        </div>
    @else
        <template x-teleport="body">
        <div x-show="open" x-cloak x-on:click.outside="open = false" class="fixed z-50 max-h-80 w-64 overflow-y-auto rounded-xl border border-gray-200 bg-white p-2 shadow-xl" x-bind:style="{ top: menuTop + 'px', left: menuLeft + 'px' }">
            <template x-for="option in itemTypeOptions" :key="option.value">
                <x-ui.button variant="plain" type="button" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-left text-sm hover:bg-sky-50" x-on:click="choose($el, index, option.value)">
                    <i class="fa-solid w-4 text-slate-500" x-bind:class="option.icon" aria-hidden="true"></i><span x-text="option.label"></span>
                </x-ui.button>
            </template>
        </div>
        </template>
    @endif
</div>
