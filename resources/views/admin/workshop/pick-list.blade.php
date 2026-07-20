<x-layout>
    <x-mast backRoute="workshop.index" backTitle="Workshops">Workshop Pick List</x-mast>

    <x-container>
        <x-ui.toolbar class="mb-4 rounded-lg border border-gray-200 bg-gray-50 p-4 flex">
            <x-slot:left>
                <div class="flex flex-col">
                    <div class="text-lg font-semibold mb-2">{{ $workshop->title }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Starts:</span> {{ $workshop->starts_at?->format('M j, Y g:i a') ?? '-' }}</div>
                    <div class="text-sm text-gray-600"><span class="font-bold w-20 inline-block">Location:</span> {{ $workshop->getLocationName() }}</div>
                    <div class="text-sm text-gray-600">
                        <span class="font-bold w-20 inline-block">Template:</span>
                        @if($workshop->pick_list_is_customized)
                            Custom{{ $workshop->pickListTemplate?->name ? ' (Originally '.$workshop->pickListTemplate?->name.')' : '' }}
                        @else
                            {{ $workshop->pickListTemplate?->name ?? 'Custom' }}
                        @endif
                    </div>
                </div>
            </x-slot:left>
            <x-slot:right>
                <x-ui.button class="mr-2" color="outline" href="{{ route('admin.workshop.edit', $workshop) }}">Edit Workshop</x-ui.button>
                @if($workshop->pick_list_template_id || $workshop->pick_list_is_customized)
                    <x-ui.button color="outline" href="{{ route('admin.workshop.pick-list.pdf', $workshop) }}" target="_blank">View PDF</x-ui.button>
                @endif
            </x-slot:right>
        </x-ui.toolbar>
    </x-container>

    <x-container>
        @php
            $pickListParticipantsInput = $workshop->registration === 'tickets'
                ? (string) $participants
                : (string) old('pick_list_participants', $workshop->pick_list_participants ?? $participants);
        @endphp

        <form
            method="POST"
            action="{{ route('admin.workshop.pick-list.save', $workshop) }}"
            class="rounded-lg border border-gray-200 p-4 mb-6 bg-white"
            x-data="workshopPickListPage({
                saveUrl: @js(route('admin.workshop.pick-list.save', $workshop)),
                csrfToken: @js(csrf_token()),
                templateItems: @js($templateItems ?? []),
                customItems: @js($customItems ?? []),
                itemSuggestions: @js($itemSuggestions ?? []),
                isCustomized: @js((bool) $isCustomized),
                checkedItemIds: @js(collect($checkedItemIds ?? [])->map(fn ($id) => (string) $id)->values()->all()),
                participantsInput: @js($pickListParticipantsInput),
                notes: @js((string) old('pick_list_notes', $pickListNotes ?? '')),
                defaultParticipants: @js((int) $participants),
                pickListCanvasDataJson: @js((string) old('pick_list_canvas_data', $pickListCanvasDataJson ?? '')),
                pickListCanvasThumbnailUrl: @js((string) ($pickListCanvasThumbnailUrl ?? '')),
                lastSavedAtIso: @js($lastSavedAt?->toIso8601String()),
                lastSavedAbsolute: @js($lastSavedAt?->format('M j, Y g:i a')),
            })"
            x-init="init()"
            x-on:submit.prevent="submitForm($event)"
        >
            @csrf
            <template x-for="id in checkedIds" :key="`checked-${id}`">
                <input type="hidden" name="checked_item_ids[]" :value="id">
            </template>
            <input
                type="hidden"
                x-bind:name="customItemsEnabled() && !itemsEditMode ? 'pick_list_custom_items' : null"
                x-bind:value="customItemsEnabled() && !itemsEditMode ? JSON.stringify(normalizeCustomItems()) : ''"
            >
            <input type="hidden" name="pick_list_canvas_data" :value="pickListCanvasDataJson || ''">
            <input type="hidden" name="pick_list_canvas_thumbnail_data" :value="pickListCanvasThumbnailData || ''">

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="text-lg font-semibold text-gray-900">Items / Materials</h2>

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <div class="flex items-center justify-between rounded-md border border-gray-200 bg-gray-50 px-3 py-2">
                        <x-ui.checkbox
                                label="All selected"
                                :noWrapper="true"
                                :inline="true"
                                x-bind:checked="allItemsChecked()"
                                x-bind:aria-checked="allItemsCheckState() === 'mixed' ? 'mixed' : String(allItemsChecked())"
                                x-effect="$el.indeterminate = allItemsCheckState() === 'mixed'"
                                x-on:change="setAllItemsChecked($event.target.checked)"
                        />
                    </div>

                    @if($workshop->registration !== 'tickets')
                        <div class="flex gap-3 items-center">
                            <span class="text-sm font-medium text-gray-700">Participants</span>
                            <x-ui.input
                                    noLabel="true"
                                    type="number"
                                    min="1"
                                    step="1"
                                    name="pick_list_participants"
                                    class="mb-0 w-12"
                                    fieldClasses="mt-0 text-center"
                                    x-model="participantsInput"
                                    x-on:input="scheduleAutosave()"
                                    x-on:change="scheduleAutosave()"
                            />
                        </div>
                    @else
                        <div class="flex gap-2 items-center">
                            <span>Participants: </span>
                            <span class="font-semibold">{{ $activeTicketCount }}</span>
                        </div>
                    @endif

                    <div class="flex flex-col sm:flex-row gap-2">
                        <x-ui.button type="button" color="outline" x-show="!itemsEditMode" x-on:click="startItemEditing()">Edit Items</x-ui.button>
                    </div>
                </div>
            </div>

            <div class="mt-4" x-show="!itemsEditMode">
                <template x-if="currentItems().length > 0">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3">
                        <template x-for="item in currentItems()" :key="item.id">
                            <label class="flex items-center gap-3 rounded-md border border-gray-200 bg-white p-3 select-none">
                                <x-ui.checkbox
                                    :noWrapper="true"
                                    :inline="true"
                                    x-model="checkedIds"
                                    x-bind:value="String(item.id)"
                                    x-on:change="scheduleAutosave()"
                                />
                                <div class="min-w-0">
                                    <div class="font-semibold" x-text="itemLabel(item)"></div>
                                    <div class="text-xs text-gray-500" x-text="typeNote(item)"></div>
                                </div>
                            </label>
                        </template>
                    </div>
                </template>
                <template x-if="currentItems().length === 0">
                    <div class="rounded-lg border border-dashed border-gray-300 bg-white p-4 text-sm text-gray-600">
                        No items yet. Click <span class="font-semibold">Edit Items</span> to add materials directly to this workshop.
                    </div>
                </template>
            </div>

            <div class="mt-4" x-show="itemsEditMode" x-cloak>
                <div class="mt-4 overflow-x-auto overflow-y-visible rounded-xl border border-gray-200 bg-white">
                    <table class="min-w-full border-collapse">
                        <thead class="hidden bg-gray-50 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 md:table-header-group">
                            <tr>
                                <th class="px-3 py-2">Item</th>
                                <th class="px-3 py-2">Type</th>
                                <th class="px-3 py-2">Quantity</th>
                                <th class="px-3 py-2">Checked</th>
                                <th class="px-3 py-2 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="block md:table-row-group">
                            <template x-if="customItems.length === 0">
                                <tr class="block border border-dashed border-gray-200 bg-gray-50 md:table-row md:border-0 md:bg-transparent">
                                    <td colspan="5" class="px-3 py-4 text-sm text-gray-600">
                                        No custom items yet. Add one to start building the pick list.
                                    </td>
                                </tr>
                            </template>
                            <template x-for="(item, index) in customItems" :key="item.id">
                                <tr class="mb-3 block rounded-xl border border-gray-200 bg-white shadow-sm md:mb-0 md:table-row md:rounded-none md:border-0 md:bg-transparent md:shadow-none align-top">
                                    <td class="block border-t border-gray-100 px-3 py-3 first:border-t-0 md:table-cell md:border-t md:px-3 md:py-3">
                                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:hidden">Item</div>
                                        <x-ui.input
                                            name="custom_item_name"
                                            label="Item"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            :suggestions="$itemSuggestions ?? []"
                                            x-model="item.item_name"
                                            x-on:input="item.item_name = $event.target.value; handleCustomItemChange(index)"
                                        />
                                    </td>
                                    <td class="block border-t border-gray-100 px-3 py-3 first:border-t-0 md:table-cell md:border-t md:px-3 md:py-3">
                                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:hidden">Type</div>
                                        <x-ui.select
                                            name="custom_item_type"
                                            label="Type"
                                            :noLabel="true"
                                            class="mb-0"
                                            x-model="item.quantity_type"
                                            x-on:change="item.quantity_type = $event.target.value; handleCustomItemChange(index)">
                                            <option value="per_participant">Per Participant</option>
                                            <option value="fixed">Fixed amount</option>
                                        </x-ui.select>
                                    </td>
                                    <td class="block border-t border-gray-100 px-3 py-3 first:border-t-0 md:table-cell md:border-t md:px-3 md:py-3">
                                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:hidden">Quantity</div>
                                        <x-ui.input
                                            type="number"
                                            name="custom_item_quantity"
                                            label="Quantity"
                                            :noLabel="true"
                                            class="mb-0"
                                            fieldClasses="mt-0"
                                            min="1"
                                            step="1"
                                            x-model="item.quantity_value"
                                            x-on:input="item.quantity_value = $event.target.value; handleCustomItemChange(index)"
                                            x-on:blur="normalizeCustomItemQuantity(index); handleCustomItemChange(index)"
                                        />
                                    </td>
                                    <td class="block border-t border-gray-100 px-3 py-3 first:border-t-0 md:table-cell md:border-t md:px-3 md:py-3">
                                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:hidden">Checked</div>
                                        <x-ui.checkbox
                                            label="Include on list"
                                            :small="true"
                                            :noWrapper="true"
                                            :inline="true"
                                            x-model="checkedIds"
                                            x-bind:value="String(item.id)"
                                            x-on:change="scheduleAutosave()"
                                        />
                                    </td>
                                    <td class="block border-t border-gray-100 px-3 py-3 first:border-t-0 md:table-cell md:border-t md:px-3 md:py-3">
                                        <div class="mb-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500 md:hidden">Actions</div>
                                        <div class="flex flex-wrap items-center gap-3 md:justify-end">
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveCustomItemUp(index)" :disabled="index === 0" title="Move up">
                                                <i class="fa-solid fa-arrow-up"></i>
                                            </button>
                                            <button type="button" class="text-gray-700 hover:text-primary-color disabled:text-gray-300" x-on:click="moveCustomItemDown(index)" :disabled="index === customItems.length - 1" title="Move down">
                                                <i class="fa-solid fa-arrow-down"></i>
                                            </button>
                                            <button type="button" class="text-red-600 hover:text-red-700" x-on:click="removeCustomItem(index)" title="Remove">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>

                <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" color="outline" x-on:click="cancelItemEditing()" x-bind:disabled="saving">Cancel</x-ui.button>
                    <x-ui.button type="button" color="secondary" x-bind:disabled="saving" x-on:click="stopItemEditing()">Save &amp; Close Editor</x-ui.button>
                </div>
            </div>

            <h2 class="mt-8 text-lg font-semibold text-gray-900">Workshop Notes</h2>

            <div class="mt-2 rounded-2xl bg-white">
                <textarea
                    id="pick_list_notes"
                    name="pick_list_notes"
                    rows="4"
                    x-ref="pickListNotes"
                    class="disabled:bg-gray-100 bg-white block w-full resize-none overflow-hidden rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-900 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300 focus:ring-indigo-300 min-h-28"
                    x-model="notes"
                    x-on:input="resizeNotesField(); scheduleAutosave()"
                ></textarea>
            </div>

            <h2 class="mt-8 text-lg font-semibold text-gray-900">Sketch Pad</h2>

            <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" x-bind:class="canvasToolButtonClass('draw')" x-on:click="setCanvasTool('draw')"><i class="fa-solid fa-pen"></i><span>Draw</span></button>
                <button type="button" x-bind:class="canvasToolButtonClass('erase')" x-on:click="setCanvasTool('erase')"><i class="fa-solid fa-eraser"></i><span>Erase</span></button>
                <button type="button" x-bind:class="canvasToolButtonClass('pan')" x-on:click="setCanvasTool('pan')"><i class="fa-solid fa-hand"></i><span>Pan</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-bind:disabled="!canvasCanUndo" x-on:click="undoCanvas()"><i class="fa-solid fa-rotate-left"></i><span>Undo</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-bind:disabled="!canvasCanRedo" x-on:click="redoCanvas()"><i class="fa-solid fa-rotate-right"></i><span>Redo</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="zoomCanvasIn()"><i class="fa-solid fa-magnifying-glass-plus"></i><span>Zoom In</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="zoomCanvasOut()"><i class="fa-solid fa-magnifying-glass-minus"></i><span>Zoom Out</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="resetCanvasView()"><i class="fa-solid fa-arrows-to-dot"></i><span>Reset View</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="clearCanvasDrawing()"><i class="fa-solid fa-trash-can"></i><span>Clear</span></button>
                <button type="button" x-bind:class="canvasActionButtonClass()" x-on:click="manualCanvasSave()"><i class="fa-solid fa-floppy-disk"></i><span>Save</span></button>
            </div>

            <div class="mt-4 flex flex-col gap-4 lg:flex-row lg:items-end">
                <label class="block">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Colour</span>
                    <input
                        type="color"
                        class="h-11 w-20 rounded-md border border-gray-300 bg-white p-1"
                        x-model="canvasColor"
                        x-on:input="setCanvasColor($event.target.value)"
                    >
                </label>
                <label class="block grow lg:max-w-xs">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Brush Size</span>
                    <div class="flex items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-3">
                        <input
                            type="range"
                            min="1"
                            max="48"
                            step="1"
                            class="w-full"
                            x-model="canvasBrushSize"
                            x-on:input="setCanvasBrushSize($event.target.value)"
                        >
                        <span class="w-12 shrink-0 text-right text-sm font-semibold text-gray-700" x-text="canvasBrushSize + 'px'"></span>
                    </div>
                </label>
                <div class="text-sm text-gray-500 lg:ml-auto">
                    Zoom <span class="font-semibold text-gray-700" x-text="canvasZoomPercent + '%'"></span>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-gray-300 bg-white p-3 shadow-sm">
                <div class="mb-3 flex flex-col gap-2 text-xs text-gray-500 sm:flex-row sm:items-center sm:justify-between">
                    <div>Apple Pencil, touch, and mouse are supported. Use Pan mode to move around the canvas, and the zoom controls to change scale.</div>
                    <div class="font-medium text-gray-600" x-show="canvasLoading">Loading canvas...</div>
                </div>

                <div x-show="canvasError" class="mb-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700" x-text="canvasError"></div>

                <div
                    x-ref="pickListCanvasViewport"
                    class="relative h-[72vh] min-h-[480px] w-full overflow-hidden rounded-xl border border-dashed border-gray-300 bg-white"
                    style="touch-action: none; overscroll-behavior: contain;"
                >
                    <canvas x-ref="pickListCanvas" class="absolute inset-0 block h-full w-full"></canvas>
                </div>
            </div>

            <div class="mt-4 flex flex-col gap-2 md:flex-row md:items-center md:justify-end">
                <div class="text-xs text-gray-500 md:mr-2" x-show="lastSavedAbsolute">
                    Last saved <span x-text="lastSavedAbsolute"></span><span x-show="lastSavedRelative"> (<span x-text="lastSavedRelative"></span>)</span>
                </div>
                <div class="text-xs text-gray-500" x-show="saving">Autosaving...</div>
                <div class="text-xs text-red-600" x-show="saveError" x-text="saveError"></div>
                <x-ui.button type="submit" x-bind:disabled="submitting || itemsEditMode">
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
