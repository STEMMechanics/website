<x-layout>
    <x-mast backRoute="workshop.index" backTitle="Workshops">Run Sheet</x-mast>

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
                    <x-ui.button color="outline" href="{{ route('admin.workshop.run-sheet.pdf', $workshop) }}" target="_blank">View PDF</x-ui.button>
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
            action="{{ route('admin.workshop.run-sheet.save', $workshop) }}"
            class="rounded-lg border border-gray-200 p-4 mb-6 bg-white"
            x-data="workshopPickListPage({
                saveUrl: @js(route('admin.workshop.run-sheet.save', $workshop)),
                csrfToken: @js(csrf_token()),
                templateItems: @js($templateItems ?? []),
                customItems: @js($customItems ?? []),
                itemSuggestions: @js($itemSuggestions ?? []),
                isCustomized: @js((bool) $isCustomized),
                checkedItemIds: @js(collect($checkedItemIds ?? [])->map(fn ($id) => (string) $id)->values()->all()),
                completedTaskIds: @js(collect($completedTaskIds ?? [])->map(fn ($id) => (string) $id)->values()->all()),
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
            x-on:sm-editor-updated.window="if ($event.detail?.name === 'workshop_run_sheet') scheduleAutosave()"
        >
            @csrf
            <template x-for="id in checkedIds" :key="`checked-${id}`">
                <input type="hidden" name="checked_item_ids[]" :value="id">
            </template>
            <template x-for="id in completedTaskIds" :key="`completed-task-${id}`">
                <input type="hidden" name="completed_task_ids[]" :value="id">
            </template>
            <input
                type="hidden"
                x-bind:name="customItemsEnabled() && !itemsEditMode ? 'pick_list_custom_items' : null"
                x-bind:value="customItemsEnabled() && !itemsEditMode ? JSON.stringify(normalizeCustomItems()) : ''"
            >
            <input type="hidden" name="pick_list_canvas_data" :value="pickListCanvasDataJson || ''">
            <input type="hidden" name="pick_list_canvas_thumbnail_data" :value="pickListCanvasThumbnailData || ''">

            <details open class="group mb-8">
                <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                    <i class="fa-solid fa-chevron-right text-sm text-gray-500 transition-transform group-open:rotate-90"></i>
                    <h2 class="text-lg font-semibold text-gray-900">Workshop Notes</h2>
                </summary>
                <textarea
                    id="pick_list_notes"
                    name="pick_list_notes"
                    rows="4"
                    x-ref="pickListNotes"
                    class="mt-2 disabled:bg-gray-100 bg-white block w-full resize-none overflow-hidden rounded-xl border border-gray-200 px-3 py-3 text-sm text-gray-900 appearance-none focus:outline-none focus:ring-0 focus:border-indigo-300 focus:ring-indigo-300 min-h-28"
                    x-model="notes"
                    x-on:input="resizeNotesField(); scheduleAutosave()"
                ></textarea>
            </details>

            @if($workshop->pickListTemplate)
                <template x-teleport="#workshop-plan-tasks">
                <div x-data="{ taskNote: null, taskName: '', taskSubtasks: [] }">
                    @if($workshop->pickListTemplate->tasks->isEmpty())
                        <p class="mt-2 text-sm text-gray-600">No template tasks.</p>
                    @else
                        @php($taskGroups = \App\Support\WorkshopTaskPresenter::grouped($workshop->pickListTemplate->tasks))
                        <div class="mt-3 grid grid-cols-1 gap-3 lg:grid-cols-2">
                            @foreach($taskGroups as $taskGroup)
                                <section class="pl-8">
                                    @if($taskGroup['heading'])
                                        <h3 class="font-semibold text-primary-color">{{ $taskGroup['heading'] }}</h3>
                                    @elseif(count($taskGroups) > 1)
                                        <h3 class="font-semibold text-primary-color">Other Tasks</h3>
                                    @endif
                                    <ul>
                                        @foreach($taskGroup['tasks'] as $presentedTask)
                                            @php($task = $presentedTask['task'])
                                            @php($taskReminder = collect($taskRemindersBySourceId ?? [])->get((string) $task->id))
                                            @php($renderedTaskNote = app(\App\Services\ReminderService::class)->renderWorkshopPlaceholders($task->notes, $workshop) ?? '')
                                            @php($renderedSubtasks = collect($task->subtasks ?? [])->map(fn ($subtask) => [
                                                'title' => $subtask['title'] ?? '',
                                                'content' => app(\App\Services\ReminderService::class)->renderWorkshopPlaceholders($subtask['content'] ?? '', $workshop) ?? '',
                                            ])->values()->all())
                                            <li id="task-{{ $task->id }}" class="scroll-mt-24 flex gap-2 rounded-md px-1 py-1.5 target:ring-2 target:ring-primary-color/20">
                                                <x-ui.checkbox
                                                    :noWrapper="true"
                                                    :inline="true"
                                                    x-model="completedTaskIds"
                                                    value="{{ (string) $task->id }}"
                                                    x-on:change="scheduleAutosave()"
                                                />
                                                <div class="min-w-0 flex-1 content-center">
                                                    <div class="font-semibold" x-bind:class="completedTaskIds.includes(@js((string) $task->id)) ? 'text-gray-400 line-through' : ''">{{ $presentedTask['label'] }}</div>
                                                    <div class="flex gap-3 items-center">
                                                        @if($task->notes || count($task->subtasks ?? []) > 0)
                                                            <button type="button" class="block text-xs text-primary-color hover:underline" x-on:click="taskName = @js($task->name); taskNote = @js($renderedTaskNote); taskSubtasks = @js($renderedSubtasks)"><i class="fa-regular fa-note-sticky mr-1"></i>View notes</button>
                                                        @endif

                                                        @if(($task->notes || count($task->subtasks ?? []) > 0) && $taskReminder)
                                                            <div class="border-r border-gray-300 h-4"></div>
                                                        @endif

                                                        @if($taskReminder)
                                                            @php($reminderRecipient = trim((string) ($taskReminder->recipient?->getName() ?: $taskReminder->recipient_email)))
                                                            <div class="text-xs text-gray-500" title="Email reminder{{ $reminderRecipient !== '' ? ' for '.$reminderRecipient : '' }}">
                                                                <i class="fa-regular fa-bell mr-1"></i>{{ $taskReminder->scheduled_at?->format('D j M, g:ia') ?? 'Date unavailable' }}
                                                                @if($reminderRecipient !== '')
                                                                    <span>· {{ $reminderRecipient }}</span>
                                                                @endif
                                                            </div>
                                                        @elseif($task->reminder_enabled)
                                                            @php($offsetDays = (int) ($task->reminder_offset_days ?? 0))
                                                            @php($offsetLabel = $offsetDays === 0 ? 'on the workshop date' : abs($offsetDays).' day'.(abs($offsetDays) === 1 ? '' : 's').' '.($offsetDays < 0 ? 'before' : 'after'))
                                                            @php($timeLabel = filled($task->reminder_time) ? \Illuminate\Support\Carbon::createFromFormat('H:i', (string) $task->reminder_time)->format('g:ia') : 'time not set')
                                                            <div class="text-xs text-gray-500" title="This reminder is configured on the template but has not been scheduled for this workshop.">
                                                                <i class="fa-regular fa-bell-slash mr-1"></i>Reminder configured: {{ $offsetLabel }} at {{ $timeLabel }} · Not scheduled
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </li>
                                        @endforeach
                                    </ul>
                                </section>
                            @endforeach
                        </div>
                    @endif
                    <div x-show="taskNote !== null" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4" x-on:keydown.escape.window="taskNote = null">
                        <div class="w-full max-w-lg rounded-xl border border-gray-200 bg-white p-5 shadow-xl" x-on:click.outside="taskNote = null">
                            <div class="flex items-center justify-between gap-3"><h3 class="text-lg font-semibold" x-text="taskName"></h3><button type="button" class="text-gray-500 hover:text-gray-800" x-on:click="taskNote = null"><i class="fa-solid fa-xmark"></i></button></div>
                            <div class="mt-4 text-sm text-gray-700 content" x-html="taskNote"></div>
                            <template x-for="(subtask, index) in taskSubtasks" :key="`task-note-subtask-${index}`">
                                <section class="mt-5 border-t border-gray-200 pt-4">
                                    <h4 class="font-semibold" x-text="subtask.title"></h4>
                                    <div class="mt-2 text-sm text-gray-700 content" x-html="subtask.content"></div>
                                </section>
                            </template>
                            <div class="mt-5 flex justify-end"><x-ui.button type="button" x-on:click="taskNote = null">Close</x-ui.button></div>
                        </div>
                    </div>
                </div>
                </template>
            @endif

            <details open class="group mb-8">
                <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                    <i class="fa-solid fa-chevron-right text-sm text-gray-500 transition-transform group-open:rotate-90"></i>
                    <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 flex-1">Pick List</h2>
                </summary>

                <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">

                <div class="flex flex-col sm:flex-row gap-3 sm:items-center">
                    <div class="flex items-center justify-between">
                        <x-ui.checkbox
                                label="Select all"
                                :noWrapper="true"
                                :inline="true"
                                x-bind:checked="allItemsChecked()"
                                x-bind:aria-checked="allItemsCheckState() === 'mixed' ? 'mixed' : String(allItemsChecked())"
                                x-effect="$el.indeterminate = allItemsCheckState() === 'mixed'"
                                x-on:change="setAllItemsChecked($event.target.checked)"
                        />
                    </div>
                    <div class="border-gray-300 border-r h-8"></div>

                    @if($workshop->registration !== 'tickets')
                        <div class="flex gap-3 items-center">
                            <span class="text-sm font-medium text-gray-700">Participant Count</span>
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
                        <div class="border-gray-300 border-r h-8"></div>
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
                            <label class="flex items-center gap-3 p-3 select-none">
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
            </details>

            @if($workshop->pickListTemplate)
                <details open class="group mb-8">
                    <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                        <i class="fa-solid fa-chevron-right text-sm text-gray-500 transition-transform group-open:rotate-90"></i>
                        <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 flex-1">Tasks</h2>
                    </summary>
                    <div id="workshop-plan-tasks" class="mt-3"></div>
                </details>
            @endif

            <details class="group mb-8">
                <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                    <i class="fa-solid fa-chevron-right text-sm text-gray-500 transition-transform group-open:rotate-90"></i>
                    <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 flex-1">Run Sheet</h2>
                </summary>

            <div class="mt-3">
                <x-ui.editor
                    name="workshop_run_sheet"
                    label="Instructions"
                    value="{!! old('workshop_run_sheet', $workshop->workshop_run_sheet ?? $workshop->pickListTemplate?->run_sheet ?? '') !!}"
                />
                <p class="mt-1 text-xs text-gray-500">Changes apply only to this workshop and do not alter the workshop template.</p>
            </div>
            </details>

            <details class="group mb-8">
                <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                    <i class="fa-solid fa-chevron-right text-sm text-gray-500 transition-transform group-open:rotate-90"></i>
                    <h2 class="text-lg font-semibold text-gray-900 border-b border-gray-300 flex-1">Drawing</h2>
                </summary>

            <div class="mt-3">
                @include('admin.shared.drawing-canvas')
            </div>
            </details>

            @if($workshop->pickListTemplate && $workshop->pickListTemplate->attachments->isNotEmpty())
                <details open class="group mb-8">
                    <summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden">
                        <i class="fa-solid fa-chevron-right text-sm text-gray-500 transition-transform group-open:rotate-90"></i>
                        <h2 class="text-lg font-semibold text-gray-900">Attachments</h2>
                    </summary>
                    <div class="mt-3 grid grid-cols-1 gap-2 md:grid-cols-2">
                        @foreach($workshop->pickListTemplate->attachments as $attachment)
                            <div class="flex items-center gap-3 rounded-lg border border-gray-200 bg-white p-3">
                                <i class="fa-solid fa-paperclip text-gray-500"></i>
                                <div class="min-w-0 flex-1">
                                    <div class="truncate">{{ $attachment->title ?: $attachment->name }}</div>
                                    <div class="text-xs text-gray-500">{{ $attachment->file_type }} · {{ \App\Helpers::bytesToString((int) $attachment->size) }}</div>
                                </div>
                                <a href="{{ $attachment->url }}" target="_blank" class="shrink-0 text-gray-500 hover:text-primary-color" title="View attachment"><i class="fa-solid fa-eye"></i></a>
                                <a href="{{ $attachment->url }}?download=1" class="shrink-0 text-gray-500 hover:text-primary-color" title="Download attachment"><i class="fa-solid fa-download"></i></a>
                            </div>
                        @endforeach
                    </div>
                </details>
            @endif

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
