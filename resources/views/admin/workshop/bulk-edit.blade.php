<x-layout>
    <x-mast backRoute="admin.workshop.index" backTitle="Workshops">Bulk Edit Workshops</x-mast>

    <x-container>
        <details class="group my-4 rounded-xl border border-gray-200 bg-white">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold text-gray-900">
                <span>Editing {{ $selectedWorkshops->count() }} workshops</span>
                <i class="fa-solid fa-chevron-down text-sm text-gray-500 transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="divide-y divide-gray-100 border-t border-gray-200">
                @foreach($selectedWorkshops as $workshop)
                    <a href="{{ route('admin.workshop.edit', $workshop) }}" target="_blank" rel="noopener noreferrer" class="flex items-center gap-3 px-4 py-3 hover:bg-gray-50">
                        @if($workshop->hero)
                            <img src="{{ $workshop->hero->thumbnail }}" alt="" class="h-12 w-12 rounded object-cover">
                        @endif
                        <span class="min-w-0">
                            <span class="block truncate font-semibold text-gray-900">{{ $workshop->title }}</span>
                            <span class="block text-xs text-gray-500">{{ $workshop->starts_at?->format('j M Y, g:i a') }} · {{ $workshop->getLocationName() }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        </details>

        <form method="POST" action="{{ route('admin.workshop.bulk.update') }}"
              x-data="{ registration: @js(old('registration', $mixedFields['registration'] ? '__mixed' : $commonValues['registration'])) }">
            @csrf
            @method('PUT')

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-900">Fields</h2>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    @php($typeValue = old('type', $mixedFields['type'] ? '__mixed' : $commonValues['type']))
                    <x-ui.select label="Type" name="type" :value="$typeValue" class="mb-0">
                        @if($mixedFields['type'] || $typeValue === '__mixed')<option value="__mixed" @selected($typeValue === '__mixed')>Mixed</option>@endif
                        <option value="physical" @selected($typeValue === 'physical')>Physical</option>
                        <option value="online" @selected($typeValue === 'online')>Online</option>
                        <option value="stemcraft" @selected($typeValue === 'stemcraft')>STEMCraft</option>
                    </x-ui.select>

                    @php($locationValue = old('location_id', $mixedFields['location_id'] ? '__mixed' : $commonValues['location_id']))
                    <x-ui.select label="Location" name="location_id" :value="$locationValue" class="mb-0">
                        @if($mixedFields['location_id'] || $locationValue === '__mixed')<option value="__mixed" @selected($locationValue === '__mixed')>Mixed</option>@endif
                        <option value="" @selected($locationValue === '')>No location</option>
                        @foreach($locations as $location)<option value="{{ $location->id }}" @selected((string) $locationValue === (string) $location->id)>{{ $location->name }}</option>@endforeach
                    </x-ui.select>

                    @php($contactValue = old('requested_by_user_id', $mixedFields['requested_by_user_id'] ? '__mixed' : $commonValues['requested_by_user_id']))
                    @php($commonContact = ! $mixedFields['requested_by_user_id'] ? $selectedWorkshops->first()?->requestedBy : null)
                    @php($contactLabel = $contactValue === '__mixed' ? 'Mixed' : ($commonContact ? trim($commonContact->getName().' · '.$commonContact->email) : ''))
                    <div class="relative" x-data="{
                        id: @js((string) $contactValue),
                        search: @js($contactLabel),
                        results: [],
                        selectedIndex: -1,
                        searching: false,
                        sequence: 0,
                        async find() {
                            this.id = '';
                            const term = this.search.trim();
                            const sequence = ++this.sequence;
                            if (term.length < 2) { this.results = []; this.selectedIndex = -1; return; }
                            this.searching = true;
                            try {
                                const url = new URL(@js(route('admin.organisation.contact-options')), window.location.origin);
                                url.searchParams.set('search', term);
                                url.searchParams.set('include_ghost', '1');
                                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                                const data = response.ok ? await response.json() : { users: [] };
                                if (sequence === this.sequence) {
                                    this.results = data.users || [];
                                    this.selectedIndex = this.results.length > 0 ? 0 : -1;
                                }
                            } finally {
                                if (sequence === this.sequence) this.searching = false;
                            }
                        },
                        choose(item) {
                            this.id = item.id;
                            this.search = `${item.name} · ${item.email}${item.company ? ` · ${item.company}` : ''}`;
                            this.results = [];
                            this.selectedIndex = -1;
                        },
                        move(step) {
                            if (this.results.length === 0) return;
                            this.selectedIndex = (this.selectedIndex + step + this.results.length) % this.results.length;
                        },
                        apply() {
                            if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                                this.choose(this.results[this.selectedIndex]);
                            }
                        },
                        close() {
                            this.results = [];
                            this.selectedIndex = -1;
                        }
                    }" x-on:click.outside="close()">
                        <input type="hidden" name="requested_by_user_id" :value="id">
                        <label for="bulk_requested_by_search" class="mb-1 block pl-1 text-sm">Requested By</label>
                        <input id="bulk_requested_by_search" type="search" x-model="search" x-on:input.debounce.350ms="find()" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter="if (selectedIndex >= 0) { $event.preventDefault(); apply(); }" x-on:keydown.escape="close()" autocomplete="off" placeholder="Search name, email, or organisation" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="results.length > 0" aria-controls="bulk_requested_by_results" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-indigo-300">
                        <div id="bulk_requested_by_results" role="listbox" class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" x-show="search.trim().length >= 2 && !searching && results.length > 0" x-cloak>
                            <template x-for="(item, index) in results" :key="item.id">
                                <button type="button" role="option" x-bind:aria-selected="selectedIndex === index" x-bind:class="selectedIndex === index ? 'bg-sky-100' : ''" class="block w-full border-b border-gray-100 px-3 py-2 text-left last:border-0 hover:bg-sky-50" x-on:mouseenter="selectedIndex = index" x-on:click="choose(item)">
                                    <span class="block text-sm text-gray-900" x-text="item.name"></span>
                                    <span class="block text-xs text-gray-500" x-text="`${item.email}${item.company ? ` · ${item.company}` : ''}`"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    @php($hostValue = old('hosted_for_organisation_id', $mixedFields['hosted_for_organisation_id'] ? '__mixed' : $commonValues['hosted_for_organisation_id']))
                    @php($commonHost = ! $mixedFields['hosted_for_organisation_id'] ? $selectedWorkshops->first()?->hostedFor : null)
                    @php($hostLabel = $hostValue === '__mixed' ? 'Mixed' : ($commonHost?->name ?? ''))
                    <div class="relative" x-data="{
                        id: @js((string) $hostValue),
                        search: @js($hostLabel),
                        results: [],
                        selectedIndex: -1,
                        searching: false,
                        sequence: 0,
                        async find() {
                            this.id = '';
                            const term = this.search.trim();
                            const sequence = ++this.sequence;
                            if (term.length < 2) { this.results = []; this.selectedIndex = -1; return; }
                            this.searching = true;
                            try {
                                const url = new URL(@js(route('admin.organisation.options')), window.location.origin);
                                url.searchParams.set('search', term);
                                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                                const data = response.ok ? await response.json() : { organisations: [] };
                                if (sequence === this.sequence) {
                                    this.results = data.organisations || [];
                                    this.selectedIndex = this.results.length > 0 ? 0 : -1;
                                }
                            } finally {
                                if (sequence === this.sequence) this.searching = false;
                            }
                        },
                        choose(item) {
                            this.id = item.id;
                            this.search = item.label;
                            this.results = [];
                            this.selectedIndex = -1;
                        },
                        move(step) {
                            if (this.results.length === 0) return;
                            this.selectedIndex = (this.selectedIndex + step + this.results.length) % this.results.length;
                        },
                        apply() {
                            if (this.selectedIndex >= 0 && this.results[this.selectedIndex]) {
                                this.choose(this.results[this.selectedIndex]);
                            }
                        },
                        close() {
                            this.results = [];
                            this.selectedIndex = -1;
                        }
                    }" x-on:click.outside="close()">
                        <input type="hidden" name="hosted_for_organisation_id" :value="id">
                        <label for="bulk_hosted_for_search" class="mb-1 block pl-1 text-sm">Hosted For</label>
                        <input id="bulk_hosted_for_search" type="search" x-model="search" x-on:input.debounce.350ms="find()" x-on:keydown.down.prevent="move(1)" x-on:keydown.up.prevent="move(-1)" x-on:keydown.enter="if (selectedIndex >= 0) { $event.preventDefault(); apply(); }" x-on:keydown.escape="close()" autocomplete="off" placeholder="Search organisations" role="combobox" aria-autocomplete="list" x-bind:aria-expanded="results.length > 0" aria-controls="bulk_hosted_for_results" class="block w-full rounded-lg border border-gray-300 bg-white px-2.5 py-2.5 text-sm text-gray-900 focus:border-indigo-300 focus:outline-none focus:ring-indigo-300">
                        <div id="bulk_hosted_for_results" role="listbox" class="absolute z-40 mt-1 max-h-72 w-full overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" x-show="search.trim().length >= 2 && !searching && results.length > 0" x-cloak>
                            <template x-for="(item, index) in results" :key="item.id">
                                <button type="button" role="option" x-bind:aria-selected="selectedIndex === index" x-bind:class="selectedIndex === index ? 'bg-sky-100' : ''" class="block w-full border-b border-gray-100 px-3 py-2 text-left text-sm last:border-0 hover:bg-sky-50" x-on:mouseenter="selectedIndex = index" x-on:click="choose(item)" x-text="item.label"></button>
                            </template>
                        </div>
                    </div>

                    @php($statusValue = old('status', $mixedFields['status'] ? '__mixed' : $commonValues['status']))
                    <x-ui.select label="Status" name="status" :value="$statusValue" class="mb-0">
                        @if($mixedFields['status'] || $statusValue === '__mixed')<option value="__mixed" @selected($statusValue === '__mixed')>Mixed</option>@endif
                        @foreach(['draft' => 'Draft', 'scheduled' => 'Opens Soon', 'open' => 'Open', 'full' => 'Full', 'closed' => 'Closed', 'cancelled' => 'Cancelled'] as $value => $label)
                            <option value="{{ $value }}" @selected($statusValue === $value)>{{ $label }}</option>
                        @endforeach
                    </x-ui.select>

                    <div class="grid grid-cols-2 gap-4">
                        @foreach(['is_private' => 'Private', 'is_hidden' => 'Hidden'] as $field => $label)
                            @php($booleanValue = old($field, $mixedFields[$field] ? '__mixed' : $commonValues[$field]))
                            <div>
                                <input x-ref="{{ $field }}State" type="hidden" name="{{ $field }}" value="{{ $booleanValue }}">
                                <x-ui.checkbox
                                    :label="$label"
                                    :checked="$booleanValue === '1'"
                                    :mixed="$booleanValue === '__mixed'"
                                    x-on:change="$refs.{{ $field }}State.value = $el.checked ? '1' : '0'"
                                    class="mb-0"
                                />
                            </div>
                        @endforeach
                    </div>

                    <x-ui.input label="Price" name="price" :value="$commonValues['price']" :placeholder="$mixedFields['price'] ? 'Mixed' : ''" class="mb-0" />
                    <x-ui.input label="Ages" name="ages" :value="$commonValues['ages']" :placeholder="$mixedFields['ages'] ? 'Mixed' : ''" class="mb-0" />

                    @php($registrationValue = old('registration', $mixedFields['registration'] ? '__mixed' : $commonValues['registration']))
                    <x-ui.select label="Registration" name="registration" :value="$registrationValue" x-model="registration" class="mb-0">
                        @if($mixedFields['registration'] || $registrationValue === '__mixed')<option value="__mixed" @selected($registrationValue === '__mixed')>Mixed</option>@endif
                        <option value="none">None</option>
                        <option value="tickets">Tickets</option>
                        <option value="interest">Interest</option>
                        <option value="link">External Link</option>
                        <option value="email">External Email</option>
                        <option value="message">Custom Message</option>
                    </x-ui.select>

                    <div>
                        <div x-show="['link', 'email', 'message'].includes(registration)">
                            <x-ui.input label="Registration Extra" name="registration_data" :value="$commonValues['registration_data']" :placeholder="$mixedFields['registration_data'] ? 'Mixed' : ''" class="mb-0" />
                        </div>
                        <div x-show="registration === 'tickets'">
                            <x-ui.input type="number" min="1" step="1" label="Max Tickets" name="max_tickets" :value="$commonValues['max_tickets']" :placeholder="$mixedFields['max_tickets'] ? 'Mixed' : ''" class="mb-0" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Categories</h2>
                @if($linkedCategories->isNotEmpty())
                    <div class="mb-5 flex flex-wrap gap-2">
                        @foreach($linkedCategories as $linked)
                            <span x-data="{ removed: @js(in_array((string) $linked['category']->id, array_map('strval', old('remove_category_ids', [])), true)) }">
                                <input
                                    x-bind:disabled="!removed"
                                    type="hidden"
                                    name="remove_category_ids[]"
                                    value="{{ $linked['category']->id }}"
                                    @disabled(! in_array((string) $linked['category']->id, array_map('strval', old('remove_category_ids', [])), true))
                                >
                                <span x-show="!removed" class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1.5 text-xs font-semibold text-sky-800">
                                    <span class="inline-flex h-3 w-3 items-center justify-center">
                                        <i class="{{ $linked['category']->iconClass() }}"></i>
                                    </span>
                                    <span>{{ $linked['category']->name }} · <span class="font-normal">({{ $linked['count'] }}/{{ $linked['total'] }})</span></span>
                                    <button type="button" x-on:click="removed = true" class="hover:text-red-700" aria-label="Remove {{ $linked['category']->name }}">
                                        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    </button>
                                </span>
                            </span>
                        @endforeach
                    </div>
                @endif

                <h3 class="mb-2 text-sm font-semibold text-gray-800">Add categories to all selected workshops</h3>
                <div class="grid gap-4 sm:grid-cols-2 md:grid-cols-4 lg:grid-cols-5">
                    @foreach($workshopCategories as $category)
                        <label class="flex cursor-pointer items-center text-sm">
                            <x-ui.checkbox
                                    name="add_category_ids[]"
                                    value="{{ $category->id }}"
                                    :checked="in_array((string) $category->id, array_map('strval', old('add_category_ids', [])), true)"
                                    :noWrapper="true"
                            />
                            <span class="inline-flex h-8 w-8 items-center justify-center text-gray-600">
                                <i class="{{ $category->iconClass() }}"></i>
                            </span>
                            <span class="font-medium">{{ $category->name }}</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="mb-8 flex flex-wrap justify-end gap-2">
                <x-ui.button href="{{ route('admin.workshop.index') }}" color="outline">Cancel</x-ui.button>
                <x-ui.button type="submit">Update {{ $selectedWorkshops->count() }} Workshops</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
