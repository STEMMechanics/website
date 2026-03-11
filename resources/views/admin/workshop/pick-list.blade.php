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
            x-data="SM.workshopPickListPage({
                saveUrl: @js(route('admin.workshop.pick-list.save', $workshop)),
                csrfToken: @js(csrf_token()),
                templateItems: @js($templateItems ?? []),
                allItemIds: @js(collect($templateItems ?? [])->pluck('id')->map(fn ($id) => (string) $id)->values()->all()),
                checkedItemIds: @js(collect($checkedItemIds ?? [])->map(fn ($id) => (string) $id)->values()->all()),
                participantsInput: @js((string) old('pick_list_participants', $workshop->pick_list_participants ?? $participants)),
                notes: @js((string) old('pick_list_notes', $workshop->pick_list_notes ?? '')),
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
            <input type="hidden" name="pick_list_canvas_data" :value="pickListCanvasDataJson || ''">
            <input type="hidden" name="pick_list_canvas_thumbnail_data" :value="pickListCanvasThumbnailData || ''">

            @if(! $workshop->pickListTemplate)
                <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-900">
                    Select a template to generate this workshop's pick list.
                </div>
            @else
                <div class="ml-auto w-48">
                    <x-ui.input label="Participants" inline type="number" min="1" max="5000" step="1" name="pick_list_participants" x-model="participantsInput" x-on:input="scheduleAutosave()" />
                </div>

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
            <div class="mt-8 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Workshop Notes</h2>
                </div>

                <div class="mt-4 rounded-2xl bg-white">
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
            </div>

            <div class="mt-8 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Sketch Pad</h2>
                </div>

                <div class="mt-4 flex flex-wrap gap-2">
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
