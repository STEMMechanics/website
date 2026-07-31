@php
    $selectedOrganisationIds = collect(request()->query('organisation_ids', []))
        ->map(fn ($id) => (string) $id)
        ->filter()
        ->values()
        ->all();
    if ($selectedOrganisationIds === [] && request()->filled('organisation_id')) {
        $selectedOrganisationIds = [(string) request('organisation_id')];
    }
    $organisationOptions = $organisations
        ->map(fn ($organisation) => [
            'id' => (string) $organisation->id,
            'label' => ($organisation->parent ? $organisation->parent->name.' — ' : '').$organisation->name,
        ])
        ->values();
    $selectedOrganisations = $organisationOptions
        ->whereIn('id', $selectedOrganisationIds)
        ->values();
    $matrixQuery = request()->except('organisation_id');
    $csvRoute = route('admin.workshop.coverage.csv', $matrixQuery);
    $pdfRoute = route('admin.workshop.coverage.pdf', $matrixQuery);
@endphp

<form
        method="GET"
        action="{{ route('admin.workshop.coverage') }}"
        class="mb-6 overflow-visible rounded-b-xl border border-gray-200 bg-white shadow-sm"
        x-data="{
        organisationSearch: '',
        activeResultIndex: 0,
        organisations: @js($organisationOptions),
        selected: @js($selectedOrganisations),
        get results() {
            const term = this.organisationSearch.trim().toLowerCase();
            if (term.length < 2) return [];
            return this.organisations
                .filter(organisation => !this.selected.some(selected => selected.id === organisation.id))
                .filter(organisation => organisation.label.toLowerCase().includes(term))
                .slice(0, 25);
        },
        add(organisation) {
            if (!organisation) return;
            this.selected.push(organisation);
            this.organisationSearch = '';
            this.activeResultIndex = 0;
        },
        remove(id) {
            this.selected = this.selected.filter(organisation => organisation.id !== id);
        },
        moveResult(direction) {
            if (this.results.length === 0) return;
            this.activeResultIndex = (this.activeResultIndex + direction + this.results.length) % this.results.length;
        },
        chooseActiveResult() {
            if (this.organisationSearch.trim().length < 2 || this.results.length === 0) return;
            this.add(this.results[this.activeResultIndex] || this.results[0]);
        },
        closeResults() {
            this.organisationSearch = '';
            this.activeResultIndex = 0;
        }
    }"
>
    <div class="flex flex-col gap-4 p-4">
        <section>
            <div class="mb-2 flex items-end justify-between gap-3">
                <label for="matrix_organisation_search" class="block text-sm font-medium text-gray-900">Organisations</label>
                <span class="text-xs text-gray-500"><span x-text="selected.length">{{ count($selectedOrganisationIds) }}</span> selected</span>
            </div>

            <div class="relative" x-on:click.outside="closeResults()">
                <input
                        id="matrix_organisation_search"
                        type="search"
                        x-model="organisationSearch"
                        x-on:input="activeResultIndex = 0"
                        x-on:keydown.down.prevent="moveResult(1)"
                        x-on:keydown.up.prevent="moveResult(-1)"
                        x-on:keydown.enter="if (organisationSearch.trim().length >= 2) { $event.preventDefault(); chooseActiveResult(); }"
                        x-on:keydown.escape.prevent="closeResults()"
                        autocomplete="off"
                        placeholder="Search and add organisations"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-controls="matrix_organisation_results"
                        x-bind:aria-expanded="organisationSearch.trim().length >= 2"
                        class="block w-full rounded-lg border border-gray-300 bg-white px-3 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-indigo-300"
                >
                <div
                        id="matrix_organisation_results"
                        class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg"
                        x-show="organisationSearch.trim().length >= 2"
                        x-cloak
                        role="listbox"
                >
                    <template x-for="(organisation, index) in results" :key="organisation.id">
                        <button
                                type="button"
                                class="flex w-full items-center gap-3 border-b border-gray-100 px-3 py-2 text-left text-sm last:border-b-0 hover:bg-sky-50"
                                x-bind:class="{ 'bg-sky-50': index === activeResultIndex }"
                                x-on:mouseenter="activeResultIndex = index"
                                x-on:click="add(organisation)"
                                x-effect="if (index === activeResultIndex) $el.scrollIntoView({ block: 'nearest' })"
                                role="option"
                                x-bind:aria-selected="index === activeResultIndex"
                        >
                            <i class="fa-solid fa-plus text-primary-color"></i>
                            <span x-text="organisation.label"></span>
                        </button>
                    </template>
                    <div class="px-3 py-3 text-sm text-gray-500" x-show="results.length === 0">No unselected organisations found.</div>
                </div>
                <p class="mt-3 text-sm text-gray-500" x-show="selected.length === 0">No organisations selected.</p>
                <p class="mt-1 text-xs text-gray-500">Enter at least 2 characters. Results are limited to 25 matches.</p>
            </div>

            <div class="mt-3 flex flex-wrap gap-2" x-show="selected.length > 0">
                <template x-for="(organisation, index) in selected" :key="organisation.id">
                    <span class="inline-flex max-w-full items-center gap-1.5 rounded-full bg-sky-50 py-1 pl-3 pr-1.5 text-sm text-sky-800 ring-1 ring-inset ring-sky-200">
                        <input type="hidden" x-bind:name="`organisation_ids[${index}]`" :value="organisation.id">
                        <span class="truncate" x-text="organisation.label"></span>
                        <button
                                type="button"
                                class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-sky-500 hover:bg-sky-100 hover:text-red-600"
                                x-on:click="remove(organisation.id)"
                                title="Remove organisation"
                                aria-label="Remove organisation"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </span>
                </template>
            </div>
        </section>

        <section>
            <div class="grid gap-x-4 gap-y-2 md:grid-cols-2 xl:grid-cols-4">
                <x-ui.input label="Workshop title" name="search" value="{{ request('search') }}" placeholder="All workshops" />
                <x-ui.select label="Category" name="category_id">
                    <option value="">All categories</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}" @selected(request('category_id') === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </x-ui.select>
                <x-ui.input type="date" label="From date" name="date_from" value="{{ request('date_from') }}" />
                <x-ui.input type="date" label="To date" name="date_to" value="{{ request('date_to') }}" />
                <x-ui.checkbox label="Past workshops only" name="past_only" value="1" :checked="request()->boolean('past_only')" />
                <x-ui.checkbox label="Include cancelled and drafts" name="include_cancelled" value="1" :checked="request()->boolean('include_cancelled')" />
            </div>
        </section>
    </div>

    <div class="flex flex-wrap items-center justify-end gap-3 border-t border-gray-200 bg-gray-50 px-4 py-3 rounded-b-xl">
        <x-ui.button color="outline" href="{{ route('admin.workshop.coverage') }}">Clear selection</x-ui.button>
        <x-ui.button type="submit">Build matrix</x-ui.button>
    </div>
</form>
