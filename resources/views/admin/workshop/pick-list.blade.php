<x-layout>
    <x-mast backRoute="workshop.index" backTitle="Workshops">Workshop Pick List</x-mast>

    <x-container>
        <x-ui.toolbar class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 flex">
            <x-slot:left>
                <div class="flex flex-col">
                    <div class="text-lg font-semibold mb-2">{{ $workshop->title }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Starts:</span> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Location:</span> {{ $workshop->getLocationName() }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Template:</span> {{ $workshop->pickListTemplate->name ?? '-' }}</div>
                </div>
            </x-slot:left>
            <x-slot:right>
                <x-ui.button class="mr-2" type="link" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                @if($workshop->pick_list_template_id)
                    <x-ui.button type="link" color="outline" href="{{ route('admin.workshop.pick-list.pdf', $workshop) }}" target="_blank">View PDF</x-ui.button>
                @endif
            </x-slot:right>
        </x-ui.toolbar>
    </x-container>

    <x-container>
        <form
            method="POST"
            action="{{ route('admin.workshop.pick-list.save', $workshop) }}"
            class="rounded-lg border border-gray-200 p-4 mb-6 bg-white"
            x-data="{
                saveUrl: @js(route('admin.workshop.pick-list.save', $workshop)),
                csrfToken: @js(csrf_token()),
                templateItems: @js($templateItems ?? []),
                allItemIds: @js(collect($templateItems ?? [])->pluck('id')->map(fn ($id) => (string) $id)->values()->all()),
                checkedIds: @js(collect($checkedItemIds ?? [])->map(fn ($id) => (string) $id)->values()->all()),
                participantsInput: @js((string) old('pick_list_participants', $workshop->pick_list_participants ?? $participants)),
                notes: @js((string) old('pick_list_notes', $workshop->pick_list_notes ?? '')),
                defaultParticipants: @js((int) $participants),
                submitting: false,
                saving: false,
                saveError: '',
                autosaveTimer: null,
                relativeTimer: null,
                lastSavedAtIso: @js($lastSavedAt?->toIso8601String()),
                lastSavedAbsolute: @js($lastSavedAt?->format('M j, Y g:i a')),
                lastSavedRelative: '',
                init() {
                    this.checkedIds = this.checkedIds.filter((id) => this.allItemIds.includes(String(id)));
                    this.relativeTimer = SM.startRelativeTimeTicker(() => {
                        this.refreshSavedRelative();
                    });
                },
                destroy() {
                    this.relativeTimer = SM.clearInterval(this.relativeTimer);
                    this.autosaveTimer = SM.clearTimer(this.autosaveTimer);
                },
                normalizeParticipants() {
                    return SM.toBoundedInt(this.participantsInput, {
                        min: 1,
                        max: 5000,
                        allowNull: true,
                    });
                },
                effectiveParticipants() {
                    return this.normalizeParticipants() ?? this.defaultParticipants;
                },
                quantityFor(item) {
                    const participants = this.effectiveParticipants();
                    const quantityValue = Math.max(1, Number.parseInt(String(item.quantity_value ?? 1), 10) || 1);
                    if (String(item.quantity_type) === 'per_participant') {
                        return Math.max(0, quantityValue * participants);
                    }
                    return quantityValue;
                },
                itemLabel(item) {
                    const quantity = this.quantityFor(item);
                    const name = String(item.item_name ?? '').trim();
                    const label = SM.pluralize(name, quantity);
                    return `${quantity} x ${label}`;
                },
                typeNote(item) {
                    if (String(item.quantity_type) !== 'per_participant') {
                        return '';
                    }
                    const quantityValue = Math.max(1, Number.parseInt(String(item.quantity_value ?? 1), 10) || 1);
                    return `(${quantityValue} per participant)`;
                },
                clearAllChecks() {
                    this.checkedIds = [];
                    this.scheduleAutosave();
                },
                checkAllItems() {
                    this.checkedIds = this.allItemIds.slice();
                    this.scheduleAutosave();
                },
                scheduleAutosave() {
                    if (this.submitting) {
                        return;
                    }
                    this.autosaveTimer = SM.scheduleDebounce(this.autosaveTimer, () => {
                        this.autosave();
                    }, 700);
                },
                async autosave() {
                    if (this.submitting || this.saving) {
                        return;
                    }

                    const payload = {
                        pick_list_participants: this.normalizeParticipants(),
                        pick_list_notes: this.notes,
                        checked_item_ids: this.checkedIds,
                    };

                    this.saving = true;
                    this.saveError = '';

                    try {
                        const data = await SM.autosaveJson(this.saveUrl, this.csrfToken, payload);
                        this.lastSavedAtIso = data.saved_at_iso ?? null;
                        this.lastSavedAbsolute = data.saved_at_display ?? null;
                        if (Array.isArray(data.checked_item_ids)) {
                            this.checkedIds = data.checked_item_ids.map((id) => String(id));
                        }
                        this.refreshSavedRelative();
                    } catch (error) {
                        this.saveError = 'Autosave failed. Use Save to retry.';
                    } finally {
                        this.saving = false;
                    }
                },
                refreshSavedRelative() {
                    this.lastSavedRelative = SM.relativeTimeFromIso(this.lastSavedAtIso);
                }
            }"
            x-init="init()"
            x-on:submit="submitting = true; autosaveTimer = SM.clearTimer(autosaveTimer);"
        >
            @csrf
            @if(! $workshop->pickListTemplate)
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                    Select a template to generate this workshop's pick list.
                </div>
            @else
                <div class="flex flex-wrap justify-end gap-3">
                    <div>
                        <label for="pick_list_participants" class="block text-sm pl-1">Participants</label>
                        <input
                            id="pick_list_participants"
                            type="number"
                            min="1"
                            max="5000"
                            step="1"
                            name="pick_list_participants"
                            class="disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 border-gray-300 focus:border-indigo-300 focus:ring-indigo-300"
                            x-model="participantsInput"
                            x-on:input="scheduleAutosave()"
                        />
                    </div>
                </div>

                <template x-for="id in checkedIds" :key="`checked-${id}`">
                    <input type="hidden" name="checked_item_ids[]" :value="id">
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 mb-2">
                    <template x-for="item in templateItems" :key="item.id">
                        <label class="flex items-center gap-3 rounded-md border border-gray-200 p-3 select-none">
                            <input
                                type="checkbox"
                                class="h-7 w-7 rounded border-gray-300"
                                x-model="checkedIds"
                                :value="String(item.id)"
                                x-on:change="scheduleAutosave()"
                            />
                            <div>
                                <div class="font-semibold" x-text="itemLabel(item)"></div>
                                <div class="text-xs text-gray-500" x-text="typeNote(item)"></div>
                            </div>
                        </label>
                    </template>
                    <div class="text-sm text-gray-600" x-show="templateItems.length === 0">This template has no items.</div>
                </div>
                <div class="flex gap-2 mb-2">
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" x-on:click="clearAllChecks()">Clear Checks</button>
                    <button type="button" class="rounded-md border border-gray-300 px-3 py-2 text-sm hover:bg-gray-50" x-on:click="checkAllItems()">Check All</button>
                </div>

            @endif
            <div class="mt-8">
                <label for="pick_list_notes" class="block text-sm pl-1">Workshop Notes</label>
                <textarea
                    id="pick_list_notes"
                    name="pick_list_notes"
                    class="disabled:bg-gray-100 bg-white block px-2.5 pb-2.5 w-full text-sm text-gray-900 rounded-lg border appearance-none focus:outline-none focus:ring-0 border-gray-300 focus:border-indigo-300 focus:ring-indigo-300 h-72"
                    x-model="notes"
                    x-on:input="scheduleAutosave()"
                ></textarea>
            </div>
            <div class="mt-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-end">
                <div class="text-xs text-gray-500 md:mr-2" x-show="lastSavedAbsolute">
                    Last saved <span x-text="lastSavedAbsolute"></span><span x-show="lastSavedRelative"> (<span x-text="lastSavedRelative"></span>)</span>
                </div>
                <div class="text-xs text-gray-500" x-show="saving">Autosaving...</div>
                <div class="text-xs text-red-600" x-show="saveError" x-text="saveError"></div>
                <x-ui.button type="submit" x-bind:disabled="submitting">
                    <span x-show="!submitting">Save</span>
                    <span x-show="submitting" class="inline-flex items-center gap-2">
                        <i class="fa-solid fa-circle-notch animate-spin"></i>
                        <span>Saving...</span>
                    </span>
                </x-ui.button>
            </div>
        </form>
    </x-container>
</x-layout>
