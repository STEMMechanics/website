@php
    $pickerOptions = collect($options ?? [])->values();
    $pickerSelectedIds = collect($selectedIds ?? [])->map('strval')->filter()->values()->all();
    $pickerSelected = $pickerOptions->whereIn('id', $pickerSelectedIds)->values();
@endphp

<section
    class="{{ $class ?? '' }}"
    x-data="{
        search: '',
        open: false,
        activeIndex: 0,
        options: @js($pickerOptions),
        selected: @js($pickerSelected),
        get results() {
            const term = this.search.trim().toLowerCase();
            return this.options
                .filter(option => !this.selected.some(selected => selected.id === option.id))
                .filter(option => option.label.toLowerCase().includes(term))
                .slice(0, 25);
        },
        add(option) {
            if (!option) return;
            this.selected.push(option);
            this.search = '';
            this.open = true;
            this.activeIndex = 0;
        },
        remove(id) {
            this.selected = this.selected.filter(option => option.id !== id);
        },
        move(direction) {
            if (this.results.length === 0) return;
            this.activeIndex = (this.activeIndex + direction + this.results.length) % this.results.length;
        },
        choose() {
            if (this.results.length === 0) return;
            this.add(this.results[this.activeIndex] || this.results[0]);
        },
        close() {
            this.open = false;
            this.search = '';
            this.activeIndex = 0;
        }
    }"
>
    <div class="mb-1 flex items-center justify-between gap-2">
        <label for="{{ $pickerId }}" class="block pl-1 text-sm">{{ $label }}</label>
        <span class="text-xs text-gray-500"><span x-text="selected.length">{{ count($pickerSelectedIds) }}</span> selected</span>
    </div>
    <div class="relative" x-on:click.outside="close()">
        <input
            id="{{ $pickerId }}"
            type="search"
            x-model="search"
            x-on:focus="open = true"
            x-on:click="open = true"
            x-on:input="open = true; activeIndex = 0"
            x-on:keydown.down.prevent="move(1)"
            x-on:keydown.up.prevent="move(-1)"
            x-on:keydown.enter="if (open && results.length > 0) { $event.preventDefault(); choose(); }"
            x-on:keydown.escape.prevent="close()"
            autocomplete="off"
            placeholder="{{ $placeholder }}"
            role="combobox"
            aria-autocomplete="list"
            aria-controls="{{ $pickerId }}_results"
            x-bind:aria-expanded="open"
            class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-indigo-300"
        >
        <div id="{{ $pickerId }}_results" class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" x-show="open" x-cloak role="listbox">
            <template x-for="(option, index) in results" :key="option.id">
                <button type="button" class="flex w-full items-center gap-3 border-b border-gray-100 px-3 py-2 text-left text-sm last:border-b-0 hover:bg-sky-50" x-bind:class="{ 'bg-sky-50': index === activeIndex }" x-on:mouseenter="activeIndex = index" x-on:click="add(option)" x-effect="if (index === activeIndex) $el.scrollIntoView({ block: 'nearest' })" role="option" x-bind:aria-selected="index === activeIndex">
                    <i class="fa-solid fa-plus text-primary-color"></i>
                    <span x-text="option.label"></span>
                </button>
            </template>
            <div class="px-3 py-3 text-sm text-gray-500" x-show="results.length === 0">No unselected matches found.</div>
        </div>
    </div>
    <div class="mt-2 flex flex-wrap gap-2" x-show="selected.length > 0">
        <template x-for="(option, index) in selected" :key="option.id">
            <span class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-sky-50 py-1 pl-3 pr-1.5 text-sm text-sky-800 ring-1 ring-inset ring-sky-200">
                <input type="hidden" x-bind:name="`{{ $fieldName }}[${index}]`" :value="option.id">
                <span class="truncate" x-text="option.label"></span>
                <button type="button" class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-sky-500 hover:bg-sky-100 hover:text-red-600" x-on:click="remove(option.id)" aria-label="Remove {{ strtolower($label) }}">
                    <i class="fa-solid fa-xmark text-xs"></i>
                </button>
            </span>
        </template>
    </div>
</section>
