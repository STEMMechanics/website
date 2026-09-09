<div x-show="workshopFormat === 'course'" x-cloak class="my-6">
    <x-ui.collapsible-section title="Course sessions" variant="product" :open="true">
        <x-slot:summary><span x-text="courseSessions.length + ' sessions · ' + courseTeachingHours().toFixed(2) + ' teaching hours'"></span></x-slot:summary>
        <div class="grid items-end gap-3 sm:grid-cols-4">
            <x-ui.input type="datetime-local" label="First session" x-model="generateStart" />
            <x-ui.input type="number" label="Weeks" min="1" max="104" x-model="generateCount" />
            <x-ui.input type="number" label="Minutes per session" min="1" x-model="generateMinutes" />
            <x-ui.button type="button" color="outline" class="mb-4" x-on:click="generateSessions()">Generate weekly sessions</x-ui.button>
        </div>
        <p class="mb-3 text-sm text-red-600" x-show="scheduleError" x-text="scheduleError"></p>
        @foreach($errors->getMessages() as $field => $messages)
            @if(str_starts_with($field, 'course_sessions'))<p class="mb-2 text-sm text-red-600">{{ $messages[0] }}</p>@endif
        @endforeach
        <div class="hidden grid-cols-[minmax(0,1fr)_minmax(0,1fr)_3rem] gap-3 border-b border-gray-200 pb-2 text-sm font-semibold md:grid" aria-hidden="true">
            <span>Starts</span><span>Ends</span><span></span>
        </div>
        <div class="divide-y divide-gray-200">
            <template x-for="(session, index) in courseSessions" :key="session.id">
                <div class="grid items-center gap-3 py-3 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_3rem]">
                    <input type="hidden" x-bind:name="`course_sessions[${index}][id]`" x-bind:value="session.id">
                    <x-ui.input class="mb-0 min-w-0" labelClass="md:sr-only" label="Starts" type="datetime-local" x-bind:name="`course_sessions[${index}][starts_at]`" x-model="session.starts_at" x-on:change="sessionChanged()" />
                    <x-ui.input class="mb-0 min-w-0" labelClass="md:sr-only" label="Ends" type="datetime-local" x-bind:name="`course_sessions[${index}][ends_at]`" x-model="session.ends_at" x-on:change="sessionChanged()" />
                    <x-ui.button type="button" variant="plain" class="self-center text-red-600" aria-label="Remove session" x-on:click="courseSessions.splice(index, 1); sessionChanged()"><i class="fa-solid fa-trash" aria-hidden="true"></i></x-ui.button>
                </div>
            </template>
        </div>
        <div class="mt-4 flex items-center justify-between gap-4">
            <x-ui.button type="button" color="outline" x-on:click="addSession()">Add session</x-ui.button>
            <span class="text-sm font-medium" x-text="courseTeachingHours().toFixed(2) + ' total teaching hours'"></span>
        </div>
    </x-ui.collapsible-section>
</div>
