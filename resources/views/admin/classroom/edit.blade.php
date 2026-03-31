@php
    $isEditing = isset($classSession) && $classSession instanceof \App\Models\ClassSession && $classSession->exists;
    $teacherValue = old('teacher_identifiers', implode(PHP_EOL, $teacherIdentifiers ?? []));
    $studentValue = old('student_identifiers', implode(PHP_EOL, $studentIdentifiers ?? []));
    $startsAtValue = old('starts_at', $isEditing ? optional($classSession->starts_at)?->format('Y-m-d\TH:i') : '');
    $endsAtValue = old('ends_at', $isEditing ? optional($classSession->ends_at)?->format('Y-m-d\TH:i') : '');
    $title = old('title', $isEditing ? $classSession->title : '');
    $slug = old('slug', $isEditing ? $classSession->slug : '');
    $roomName = old('room_name', $isEditing ? $classSession->room_name : '');
    $summary = old('summary', $isEditing ? $classSession->summary : '');
    $instructionsHtml = old('instructions_html', $isEditing ? $classSession->instructions_html : '');
    $forumCategoryChoice = old('forum_category_choice', $isEditing ? ($classSession->forum_category_id ?: '') : '');
    $forumCategoryName = old('forum_category_name', $isEditing ? ($classSession->forumCategory?->name ?? ($title ? $title.' Forum' : '')) : ($title ? $title.' Forum' : ''));
    $liveChatEnabled = old('live_chat_enabled', $isEditing ? $classSession->live_chat_enabled : true);
    $broadcastSchedule = [];
    $oldSchedule = old('broadcast_sessions_json');
    if (is_string($oldSchedule) && trim($oldSchedule) !== '') {
        $decodedSchedule = json_decode($oldSchedule, true);
        if (is_array($decodedSchedule)) {
            $broadcastSchedule = $decodedSchedule;
        }
    } elseif ($isEditing) {
        $broadcastSchedule = $classSession->broadcastSchedule();
    }
@endphp

<x-layout>
    <x-mast backRoute="admin.course.index" backTitle="Courses">{{ $isEditing ? 'Edit' : 'Create' }} Course</x-mast>

    <x-container>
        @if($isEditing)
            <div class="mb-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Open course</div>
                        <div class="text-lg font-semibold text-gray-900">{{ $classSession->title }}</div>
                    </div>
                    <x-ui.button href="{{ route('class.show', $classSession) }}" color="primary-outline">Open course</x-ui.button>
                    <x-ui.button href="{{ route('admin.course.duplicate', $classSession) }}" color="secondary">Duplicate</x-ui.button>
                </div>
            </div>
        @elseif(isset($sourceClassSession) && $sourceClassSession)
            <div class="mb-6 rounded-3xl border border-sky-200 bg-sky-50 p-5 text-sky-950">
                <div class="text-xs font-semibold uppercase tracking-wide">Duplicating from</div>
                <div class="mt-1 text-lg font-semibold">{{ $sourceClassSession->title }}</div>
                <div class="mt-1 text-sm text-sky-800">This form is prefilled from the selected course. Adjust the room, forum, and schedule before saving.</div>
            </div>
        @endif

        <form
            method="POST"
            action="{{ $isEditing ? route('admin.course.update', $classSession) : route('admin.course.store') }}"
            class="space-y-8"
            x-data="classroomAdminForm({
                title: @js($title),
                slug: @js($slug),
                roomName: @js($roomName),
                startsAt: @js($startsAtValue),
                forumCategoryChoice: @js($forumCategoryChoice),
            })"
        >
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-900">Course details</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <x-ui.input name="title" label="Title" :value="$title" x-model="titleValue" x-on:blur="autofillDerivedFields()" />
                    <x-ui.input name="slug" label="Slug" :value="$slug" info="Leave blank to auto-generate from the title and start year." x-model="slugValue" x-on:input="slugManual = true" />
                    <x-ui.input name="room_name" label="Room name" :value="$roomName" info="Leave blank to auto-generate from the slug." x-model="roomNameValue" x-on:input="roomManual = true" />
                    <div class="rounded-2xl border border-gray-200 bg-gray-50 p-4 md:col-span-2">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Access group</div>
                        <div class="mt-1 text-sm text-gray-700">This course uses the course slug as the group slug. Students in <span class="font-semibold text-gray-900">{{ $isEditing ? $classSession->slug : 'the saved slug' }}</span> can join automatically.</div>
                    </div>
                    <x-ui.select name="forum_category_choice" label="Forum category" x-model="forumCategoryChoice">
                        <option value="">No forum category</option>
                        <option disabled>────────────</option>
                        <option value="create">Create a new forum category</option>
                        <option disabled>────────────</option>
                        @foreach($forumCategories as $forumCategory)
                            <option value="{{ $forumCategory->id }}" @selected((string) $forumCategoryChoice === (string) $forumCategory->id)>{{ $forumCategory->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50 p-4" x-show="forumCategoryChoice === 'create'" x-cloak>
                        <x-ui.input name="forum_category_name" label="New forum category name" :value="$forumCategoryName" info="This creates a linked forum category for course participants." />
                    </div>
                    <x-ui.checkbox name="live_chat_enabled" label="Enable live chat" value="1" :checked="(bool) $liveChatEnabled" />
                    <x-ui.input type="datetime-local" name="starts_at" label="Starts at" :value="$startsAtValue" x-model="startsAtValue" x-on:change="autofillDerivedFields()" />
                    <x-ui.input type="datetime-local" name="ends_at" label="Ends at" :value="$endsAtValue" />
                    <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-gray-50 p-4" x-data="classroomScheduleEditor(@js($broadcastSchedule))" x-init="sync()">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Live stream schedule</h3>
                                <p class="mt-1 text-sm text-gray-600">Add the live session start and end times here. The course shows the next scheduled stream when nobody is live.</p>
                            </div>
                            <x-ui.button type="button" color="primary-outline" x-on:click="addSession()">Add session</x-ui.button>
                        </div>
                        <input type="hidden" name="broadcast_sessions_json" x-ref="output">
                        <div class="mt-4 space-y-4">
                            <template x-for="(session, index) in sessions" :key="index">
                                <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm">
                                    <div class="grid gap-4 md:grid-cols-[repeat(2,minmax(0,1fr))_auto]">
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Starts at</span>
                                            <input type="datetime-local" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" x-model="session.starts_at" x-on:input="sync()">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">Ends at</span>
                                            <input type="datetime-local" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" x-model="session.ends_at" x-on:input="sync()">
                                        </label>
                                        <div class="flex items-end justify-end">
                                            <x-ui.button type="button" color="danger-outline" x-on:click="removeSession(index)">Remove</x-ui.button>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="sessions.length === 0" class="rounded-2xl border border-dashed border-gray-300 bg-white px-4 py-6 text-sm text-gray-600">
                                No live broadcasts scheduled yet.
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input type="textarea" name="summary" label="Summary" :value="$summary" info="Short description shown on the course page." />
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-900">Instructions</h2>
                <div class="mt-4">
                    <x-ui.editor
                        name="instructions_html"
                        :label="null"
                        :value="$instructionsHtml"
                        info="Use headings to break the class into sections. The editor stores HTML and the course renders it directly for participants."
                    />
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-900">Enrolments</h2>
                <p class="mt-2 text-sm text-gray-600">Enter one identifier per line. Use a username, email address, or UUID. Students join view-only by default.</p>

                <div class="mt-6 grid gap-4 lg:grid-cols-2">
                    <x-ui.input
                        type="textarea"
                        name="teacher_identifiers"
                        label="Teacher identifiers"
                        :value="$teacherValue"
                        info="One per line. These users get teacher publishing permissions."
                    />
                    <x-ui.input
                        type="textarea"
                        name="student_identifiers"
                        label="Student identifiers"
                        :value="$studentValue"
                        info="One per line. These users join as students/viewers."
                    />
                </div>

                @if($isEditing && $classSession->enrolments->isNotEmpty())
                    <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-4">
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Current enrolments</div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @foreach($classSession->enrolments as $enrolment)
                                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-3 py-1 text-sm text-gray-700">
                                    <span class="font-semibold text-gray-900">{{ $enrolment->user?->username ?: $enrolment->user?->getName() ?: $enrolment->user_id }}</span>
                                    <span class="text-xs uppercase tracking-wide text-gray-500">{{ $enrolment->role }}</span>
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </section>

            <div class="flex flex-wrap justify-between gap-3">
                <div class="flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('admin.course.index') }}" color="secondary">Back</x-ui.button>
                    @if($isEditing)
                        <x-ui.button href="{{ route('admin.course.duplicate', $classSession) }}" color="primary-outline">Duplicate</x-ui.button>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    @if($isEditing)
                        <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete course?', 'Are you sure you want to delete this course? This action cannot be undone.', '{{ route('admin.course.destroy', $classSession) }}')">Delete</x-ui.button>
                    @endif
                    <x-ui.button type="submit">Save course</x-ui.button>
                </div>
            </div>
        </form>
    </x-container>
</x-layout>

<script>
    function classroomAdminForm(initialState) {
        return {
            titleValue: String(initialState?.title || ''),
            slugValue: String(initialState?.slug || ''),
            roomNameValue: String(initialState?.roomName || ''),
            startsAtValue: String(initialState?.startsAt || ''),
            forumCategoryChoice: String(initialState?.forumCategoryChoice || ''),
            slugManual: String(initialState?.slug || '').trim() !== '',
            roomManual: String(initialState?.roomName || '').trim() !== '',
            slugify(value) {
                return String(value || '')
                    .toLowerCase()
                    .replace(/[^a-z0-9]+/g, '-')
                    .replace(/-+/g, '-')
                    .replace(/^[-]+|[-]+$/g, '');
            },
            currentTermNumber() {
                const source = String(this.startsAtValue || '').trim();
                const date = source ? new Date(source) : new Date();
                const month = Number.isFinite(date.getMonth()) ? date.getMonth() + 1 : 1;
                return Math.max(1, Math.ceil(month / 3));
            },
            currentYear() {
                const source = String(this.startsAtValue || '').trim();
                const date = source ? new Date(source) : new Date();
                return Number.isFinite(date.getFullYear()) ? date.getFullYear() : new Date().getFullYear();
            },
            autofillDerivedFields() {
                const titleSlug = this.slugify(this.titleValue);
                const derivedSlug = `class-${titleSlug || 'class'}-term${this.currentTermNumber()}-${this.currentYear()}`;
                if (!this.slugManual && String(this.slugValue || '').trim() === '') {
                    this.slugValue = derivedSlug;
                }
                if (!this.roomManual && String(this.roomNameValue || '').trim() === '') {
                    this.roomNameValue = this.slugValue || derivedSlug;
                }
            },
        };
    }

    function classroomScheduleEditor(initialSessions) {
        return {
            sessions: Array.isArray(initialSessions)
                ? initialSessions.map((session) => ({
                    starts_at: session?.starts_at || '',
                    ends_at: session?.ends_at || '',
                }))
                : [],
            sync() {
                const payload = this.sessions
                    .map((session) => ({
                        starts_at: String(session.starts_at || '').trim(),
                        ends_at: String(session.ends_at || '').trim(),
                        label: '',
                    }))
                    .filter((session) => session.starts_at !== '' || session.ends_at !== '' || session.label !== '');

                if (this.$refs.output) {
                    this.$refs.output.value = JSON.stringify(payload);
                }
            },
            addSession() {
                this.sessions.push({
                    starts_at: '',
                    ends_at: '',
                });
                this.sync();
            },
            removeSession(index) {
                this.sessions.splice(index, 1);
                this.sync();
            },
        };
    }
</script>
