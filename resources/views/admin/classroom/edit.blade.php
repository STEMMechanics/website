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
    $accessGroupSlug = old('access_group_slug', $isEditing ? $classSession->access_group_slug : '');
    $forumCategoryId = old('forum_category_id', $isEditing ? $classSession->forum_category_id : '');
    $liveChatEnabled = old('live_chat_enabled', $isEditing ? $classSession->live_chat_enabled : true);
@endphp

<x-layout>
    <x-mast backRoute="admin.classroom.index" backTitle="Classrooms">{{ $isEditing ? 'Edit' : 'Create' }} Classroom</x-mast>

    <x-container>
        @if($isEditing)
            <div class="mb-6 rounded-3xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-center gap-3">
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wide text-gray-500">Open classroom</div>
                        <div class="text-lg font-semibold text-gray-900">{{ $classSession->title }}</div>
                    </div>
                    <x-ui.button href="{{ route('class.show', $classSession) }}" color="primary-outline">Open classroom</x-ui.button>
                    <x-ui.button href="{{ route('admin.classroom.duplicate', $classSession) }}" color="secondary">Duplicate</x-ui.button>
                </div>
            </div>
        @elseif(isset($sourceClassSession) && $sourceClassSession)
            <div class="mb-6 rounded-3xl border border-sky-200 bg-sky-50 p-5 text-sky-950">
                <div class="text-xs font-semibold uppercase tracking-wide">Duplicating from</div>
                <div class="mt-1 text-lg font-semibold">{{ $sourceClassSession->title }}</div>
                <div class="mt-1 text-sm text-sky-800">This form is prefilled from the selected classroom. Adjust the room, access group, and forum before saving.</div>
            </div>
        @endif

        <form
            method="POST"
            action="{{ $isEditing ? route('admin.classroom.update', $classSession) : route('admin.classroom.store') }}"
            class="space-y-8"
        >
            @csrf
            @if($isEditing)
                @method('PUT')
            @endif

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-900">Classroom details</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <x-ui.input name="title" label="Title" :value="$title" />
                    <x-ui.input name="slug" label="Slug" :value="$slug" info="Leave blank to auto-generate from the title." />
                    <x-ui.input name="room_name" label="Room name" :value="$roomName" info="Leave blank to auto-generate from the slug." />
                    <x-ui.input name="access_group_slug" label="Access group slug" :value="$accessGroupSlug" :suggestions="$groupSuggestions" info="Users in this group can join as students automatically." />
                    <x-ui.select name="forum_category_id" label="Forum category">
                        <option value="">None</option>
                        @foreach($forumCategories as $forumCategory)
                            <option value="{{ $forumCategory->id }}" @selected((string) $forumCategoryId === (string) $forumCategory->id)>{{ $forumCategory->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <x-ui.checkbox name="live_chat_enabled" label="Enable live chat" value="1" :checked="(bool) $liveChatEnabled" />
                    <x-ui.input type="datetime-local" name="starts_at" label="Starts at" :value="$startsAtValue" />
                    <x-ui.input type="datetime-local" name="ends_at" label="Ends at" :value="$endsAtValue" />
                    <div class="md:col-span-2">
                        <x-ui.input type="textarea" name="summary" label="Summary" :value="$summary" info="Short description shown on the classroom page." />
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
                        info="Use headings to break the class into sections. The editor stores HTML and the classroom renders it directly for participants."
                    />
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm">
                <h2 class="text-xl font-semibold text-gray-900">Enrolments</h2>
                <p class="mt-2 text-sm text-gray-600">Enter one identifier per line. Use a username, email address, or UUID. Teachers can manage the room; students join view-only by default.</p>

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
                    <x-ui.button href="{{ route('admin.classroom.index') }}" color="secondary">Back</x-ui.button>
                    @if($isEditing)
                        <x-ui.button href="{{ route('admin.classroom.duplicate', $classSession) }}" color="primary-outline">Duplicate</x-ui.button>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    @if($isEditing)
                        <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete classroom?', 'Are you sure you want to delete this classroom? This action cannot be undone.', '{{ route('admin.classroom.destroy', $classSession) }}')">Delete</x-ui.button>
                    @endif
                    <x-ui.button type="submit">Save classroom</x-ui.button>
                </div>
            </div>
        </form>
    </x-container>
</x-layout>
