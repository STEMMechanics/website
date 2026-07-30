<x-layout>
    <x-mast backRoute="admin.media.index" backTitle="Media">Bulk Edit Media</x-mast>

    <x-container>
        <details class="group my-4 rounded-xl border border-gray-200 bg-white">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-4 py-3 font-semibold text-gray-900">
                <span>Editing {{ $selectedMedia->count() }} media items</span>
                <i class="fa-solid fa-chevron-down text-sm text-gray-500 transition-transform group-open:rotate-180"></i>
            </summary>
            <div class="grid grid-cols-2 gap-3 border-t border-gray-200 p-4 sm:grid-cols-4 lg:grid-cols-6">
                @foreach($selectedMedia as $medium)
                    <a href="{{ route('admin.media.edit', $medium) }}" target="_blank" rel="noopener noreferrer" class="group/item min-w-0">
                        <div class="flex aspect-square items-center justify-center overflow-hidden rounded-lg border border-gray-200 bg-gray-100">
                            <img src="{{ $medium->thumbnail }}" alt="{{ $medium->title }}" class="h-full w-full object-contain">
                        </div>
                        <div class="mt-1 truncate text-xs font-semibold text-gray-700 group-hover/item:text-primary-color">{{ $medium->title }}</div>
                    </a>
                @endforeach
            </div>
        </details>

        <form method="POST" action="{{ route('admin.media.bulk.update') }}">
            @csrf
            @method('PUT')

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4">
                <div class="mb-4">
                    <h2 class="text-base font-semibold text-gray-900">Shared fields</h2>
                    <p class="text-sm text-gray-500">Matching values are shown. Mixed values are left blank or marked Mixed. Only values you change are applied to every selected item.</p>
                </div>

                <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                    @php($visibilityValue = old('visibility', $mixedFields['visibility'] ? '__mixed' : $commonValues['visibility']))
                    <x-ui.select label="Visibility" name="visibility" :value="$visibilityValue" class="mb-0">
                        @if($mixedFields['visibility'] || $visibilityValue === '__mixed')
                            <option value="__mixed" @selected($visibilityValue === '__mixed')>Mixed</option>
                        @endif
                        <option value="private" @selected($visibilityValue === 'private')>Private</option>
                        <option value="public" @selected($visibilityValue === 'public')>Public</option>
                    </x-ui.select>

                    @php($ownerValue = old('user_id', $mixedFields['user_id'] ? '__mixed' : $commonValues['user_id']))
                    <x-ui.select label="Owner" name="user_id" :value="$ownerValue" class="mb-0">
                        @if($mixedFields['user_id'] || $ownerValue === '__mixed')
                            <option value="__mixed" @selected($ownerValue === '__mixed')>Mixed</option>
                        @endif
                        <option value="" @selected($ownerValue === '')>Unassigned</option>
                        @foreach($mediaOwners as $owner)
                            <option value="{{ $owner->id }}" @selected((string) $ownerValue === (string) $owner->id)>
                                {{ $owner->getName() ?: $owner->email }}
                            </option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input
                        label="Photographed At"
                        name="photographed_at"
                        type="date"
                        :value="$commonValues['photographed_at']"
                        :placeholder="$mixedFields['photographed_at'] ? 'Mixed' : ''"
                        class="mb-0"
                    />
                    <div class="md:col-span-2">
                        <x-ui.input
                            label="Tags"
                            name="tags"
                            :value="$commonValues['tags']"
                            :placeholder="$mixedFields['tags'] ? 'Mixed' : ''"
                            :suggestions="$tagOptions"
                            class="mb-0"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input
                            label="Caption"
                            name="caption"
                            :value="$commonValues['caption']"
                            :placeholder="$mixedFields['caption'] ? 'Mixed' : ''"
                            class="mb-0"
                        />
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input
                            label="Notes"
                            name="consent_notes"
                            type="textarea"
                            :value="$commonValues['consent_notes']"
                            :placeholder="$mixedFields['consent_notes'] ? 'Mixed' : ''"
                            rows="3"
                            class="mb-0"
                        />
                    </div>
                </div>
            </div>

            <div class="mb-6 rounded-xl border border-gray-200 bg-white p-4"
                 x-data="bulkWorkshopEditor(@js(collect($workshopOptions)->map(function ($workshop) {
                    $location = $workshop->location?->name ?: $workshop->getLocationName();
                    $date = $workshop->starts_at ? $workshop->starts_at->format('j M Y') : 'No date';
                    return [
                        'id' => (string) $workshop->id,
                        'title' => (string) $workshop->title,
                        'meta' => $location.' · '.$date,
                        'search' => strtolower($workshop->title.' '.$location.' '.$date),
                    ];
                 })->values()), @js(old('add_workshop_ids', [])))">
                <h2 class="text-base font-semibold text-gray-900">Workshop links</h2>
                <p class="mb-4 text-sm text-gray-500">Remove affects only selected media currently linked to that workshop. Add links the workshop to every selected item.</p>

                <h3 class="mb-2 text-sm font-semibold text-gray-800">Currently linked workshops</h3>
                @if($linkedWorkshops->isEmpty())
                    <div class="mb-5 rounded-lg border border-dashed border-gray-200 bg-gray-50 p-3 text-sm text-gray-500">None of the selected media is linked to a workshop.</div>
                @else
                    <div class="mb-5 divide-y divide-gray-100 rounded-lg border border-gray-200">
                        @foreach($linkedWorkshops as $linked)
                            @php($workshop = $linked['workshop'])
                            <div class="flex items-start justify-between gap-4 px-3 py-3 hover:bg-red-50">
                                <span class="min-w-0">
                                    <span class="block font-semibold text-gray-900">{{ $workshop->title }}</span>
                                    <span class="block text-xs text-gray-500">
                                        {{ $workshop->location?->name ?: $workshop->getLocationName() }}
                                        · {{ $workshop->starts_at?->format('j M Y') ?: 'No date' }}
                                        · linked to {{ $linked['count'] }} of {{ $linked['total'] }}
                                    </span>
                                </span>
                                <x-ui.checkbox
                                    name="remove_workshop_ids[]"
                                    :value="$workshop->id"
                                    label="Remove"
                                    :checked="in_array((string) $workshop->id, array_map('strval', old('remove_workshop_ids', [])), true)"
                                    small="true"
                                    inline="true"
                                    noWrapper="true"
                                    class="shrink-0"
                                    inputClass="text-red-600"
                                    labelClass="font-semibold text-red-700"
                                />
                            </div>
                        @endforeach
                    </div>
                @endif

                <h3 class="mb-2 text-sm font-semibold text-gray-800">Add workshops to all selected media</h3>
                <template x-for="id in added" :key="id">
                    <input type="hidden" name="add_workshop_ids[]" :value="id">
                </template>
                <div class="mb-3 flex flex-wrap gap-2" x-show="added.length" x-cloak>
                    <template x-for="workshop in addedWorkshops()" :key="workshop.id">
                        <span class="inline-flex items-center gap-2 rounded-full bg-sky-100 px-3 py-1 text-xs font-semibold text-sky-800">
                            <span x-text="`${workshop.title} · ${workshop.meta}`"></span>
                            <button type="button" x-on:click="remove(workshop.id)" class="hover:text-red-600"><i class="fa-solid fa-xmark"></i></button>
                        </span>
                    </template>
                </div>
                <x-ui.input
                    type="search"
                    name=""
                    label="Search workshop title, location, or date"
                    x-model="search"
                    noLabel="true"
                    class="mb-0"
                />
                <div class="mt-2 max-h-64 overflow-y-auto rounded-lg border border-gray-200" x-show="search.trim().length >= 2" x-cloak>
                    <template x-for="workshop in results()" :key="workshop.id">
                        <button type="button" x-on:click="add(workshop.id)" class="block w-full border-b border-gray-100 px-3 py-2 text-left last:border-0 hover:bg-sky-50">
                            <span class="block text-sm font-semibold text-gray-900" x-text="workshop.title"></span>
                            <span class="block text-xs text-gray-500" x-text="workshop.meta"></span>
                        </button>
                    </template>
                    <div class="p-3 text-sm text-gray-500" x-show="results().length === 0">No workshops found.</div>
                </div>
            </div>

            <div class="mb-8 flex flex-wrap justify-end gap-2">
                <x-ui.button href="{{ route('admin.media.index') }}" color="outline">Cancel</x-ui.button>
                <x-ui.button type="submit">Update {{ $selectedMedia->count() }} Items</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    function bulkWorkshopEditor(workshops, initialAdded) {
        return {
            workshops,
            search: '',
            added: initialAdded.map(String),
            results() {
                const term = this.search.trim().toLowerCase();
                if (term.length < 2) return [];
                return this.workshops.filter((workshop) => workshop.search.includes(term) && !this.added.includes(workshop.id)).slice(0, 25);
            },
            addedWorkshops() {
                return this.workshops.filter((workshop) => this.added.includes(workshop.id));
            },
            add(id) {
                id = String(id);
                if (!this.added.includes(id)) this.added.push(id);
                this.search = '';
            },
            remove(id) {
                this.added = this.added.filter((item) => item !== String(id));
            },
        };
    }
</script>
