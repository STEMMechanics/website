<x-layout>
    <x-mast backRoute="workshop.index" backTitle="Workshops">Workshop Pick List</x-mast>

    <x-container>
        <div class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 flex">
            <div class="flex-grow">
                <div class="text-lg font-semibold">{{ $workshop->title }}</div>
                <div class="text-sm text-gray-600">Starts: {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
                <div class="text-sm text-gray-600">Location: {{ $workshop->getLocationName() }}</div>
                <div class="text-sm text-gray-600">Pick List Template: {{ $workshop->pickListTemplate->name }}</div>
            </div>
            <div class="mt-2">
                <x-ui.button class="mr-2" type="link" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                @if($workshop->pick_list_template_id)
                    <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.pick-list.pdf', $workshop) }}" target="_blank">View PDF</x-ui.button>
                @endif
            </div>
        </div>

        <form method="POST" action="{{ route('admin.workshop.pick-list.save', $workshop) }}" class="rounded-lg border border-gray-200 p-4 mb-6 bg-white" x-data="{
            checked: @js(collect($checkedItemIds ?? [])->mapWithKeys(fn ($id) => [(string) $id => true])->all()),
            allItemIds: @js($calculatedItems->pluck('item_id')->map(fn ($id) => (string) $id)->values()->all()),
            clearAllChecks() {
                this.checked = {};
            },
            checkAllItems() {
                const next = {};
                for (const id of this.allItemIds) {
                    next[id] = true;
                }
                this.checked = next;
            }
        }">
            @csrf
            @if(! $workshop->pickListTemplate)
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                    Select a template to generate this workshop's pick list.
                </div>
            @else
                <div class="flex flex-wrap justify-end gap-3">
                    <div>
                        <x-ui.input type="number" min="1" step="1" label="Participants" name="pick_list_participants" value="{{ old('pick_list_participants', $workshop->pick_list_participants) }}" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-2">
                    @forelse($calculatedItems as $index => $row)
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 p-3 select-none">
                            <input type="checkbox" class="h-7 w-7 rounded border-gray-300" x-model="checked['{{ (int) $row['item_id'] }}']" />
                            <input type="hidden" name="checked_item_ids[]" :disabled="!checked['{{ (int) $row['item_id'] }}']" value="{{ (int) $row['item_id'] }}">
                            <div>
                                <div class="font-semibold">{{ $row['quantity_text'] }} x {{ $row['item_name'] }}</div>
                                <div class="text-xs text-gray-500">{{ $row['type_note'] }}</div>
                            </div>
                        </label>
                    @empty
                        <div class="text-sm text-gray-600">This template has no items.</div>
                    @endforelse
                </div>
                <div class="flex gap-2 mb-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" x-on:click="clearAllChecks()">Clear Checks</button>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" x-on:click="checkAllItems()">Check All</button>
                </div>

            @endif
            <div class="mt-8">
                <x-ui.input
                        type="textarea"
                        label="Workshop Notes"
                        fieldClasses="h-72"
                        name="pick_list_notes"
                        value="{{ old('pick_list_notes', $workshop->pick_list_notes ?? '') }}"
                />
            </div>
            <div class="flex md:justify-end">
                <x-ui.button type="submit">Save</x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
