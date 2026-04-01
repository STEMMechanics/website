@php
    $isEditing = isset($classSession) && $classSession instanceof \App\Models\ClassSession && $classSession->exists;
    $teacherValue = old('teacher_identifiers', implode(PHP_EOL, $teacherIdentifiers ?? []));
    $studentValue = old('student_identifiers', implode(PHP_EOL, $studentIdentifiers ?? []));
    $startsAtValue = old('starts_at', $isEditing ? optional($classSession->starts_at)?->format('Y-m-d\TH:i') : '');
    $endsAtValue = old('ends_at', $isEditing ? optional($classSession->ends_at)?->format('Y-m-d\TH:i') : '');
    $title = old('title', $isEditing ? $classSession->title : '');
    $slug = old('slug', $isEditing ? $classSession->slug : '');
    $roomName = old('room_name', $isEditing ? $classSession->room_name : '');
    $heroMediaValue = old('hero_media_name', $isEditing ? $classSession->hero_media_name : ($sourceClassSession?->hero_media_name ?? ''));
    $summary = old('summary', $isEditing ? $classSession->summary : '');
    $instructionsHtml = old('instructions_html', $isEditing ? $classSession->instructions_html : '');
    $forumCategoryChoice = old('forum_category_choice', $isEditing ? ($classSession->forum_category_id ?: '') : '');
    $forumCategoryName = old('forum_category_name', $isEditing ? ($classSession->forumCategory?->name ?? ($title ? $title.' Forum' : '')) : ($title ? $title.' Forum' : ''));
    $liveChatEnabled = old('live_chat_enabled', $isEditing ? $classSession->live_chat_enabled : true);
    $studentEnrolments = $isEditing ? $classSession->enrolments->where('role', \App\Models\ClassEnrolment::ROLE_STUDENT) : collect();
    $studentEnrolmentCount = $studentEnrolments->count();
    $paidStudentUserIdSet = array_fill_keys(array_map('strval', $paidStudentUserIds ?? []), true);
    $paidStudentEnrolmentCount = $studentEnrolments->filter(fn ($enrolment) => isset($paidStudentUserIdSet[(string) $enrolment->user_id]))->count();
    $deleteWarningHtml = 'Are you sure you want to delete this course? This action cannot be undone.';
    if ($studentEnrolmentCount > 0) {
        $deleteWarningHtml = '<p class="mb-2">This course has <strong>'.$studentEnrolmentCount.' student '.($studentEnrolmentCount === 1 ? 'enrolment' : 'enrolments').'</strong>. Deleting it will remove their access immediately.</p>';
        if ($paidStudentEnrolmentCount > 0) {
            $deleteWarningHtml .= '<p class="mb-2"><strong>'.$paidStudentEnrolmentCount.' of these students paid through a workshop purchase.</strong> Refunds are not automatic and must be handled separately.</p>';
        }
        if ($isEditing && $classSession->forum_category_id) {
            $deleteWarningHtml .= '<p class="mb-0">The linked forum category will remain unless it is deleted separately.</p>';
        }
    }
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
    <x-mast backRoute="admin.course.index" backTitle="Courses">{{ $isEditing ? 'Edit ' . $classSession->title : 'Create' }} Course</x-mast>

    <x-container>
        <form
            method="POST"
            action="{{ $isEditing ? route('admin.course.update', $classSession) : route('admin.course.store') }}"
            class="space-y-6"
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

            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm md:p-6 mt-8">
                <div class="grid gap-5 md:grid-cols-2">
                    <x-ui.input name="title" label="Title" :value="$title" x-model="titleValue" x-on:blur="autofillDerivedFields()" />
                    <x-ui.input name="slug" label="Slug" :value="$slug" info="Leave blank to auto-generate from the title and start year." x-model="slugValue" x-on:input="slugManual = true" />
                    <x-ui.select name="forum_category_choice" label="Forum category" x-model="forumCategoryChoice">
                        <option value="">No forum category</option>
                        <option disabled>────────────</option>
                        <option value="create">Create a new forum category</option>
                        <option disabled>────────────</option>
                        @foreach($forumCategories as $forumCategory)
                            <option value="{{ $forumCategory->id }}" @selected((string) $forumCategoryChoice === (string) $forumCategory->id)>{{ $forumCategory->name }}</option>
                        @endforeach
                    </x-ui.select>
                    <div class="space-y-3 rounded-2xl border border-gray-200 bg-gray-50 p-4 md:col-span-2" x-show="forumCategoryChoice === 'create'" x-cloak>
                        <x-ui.input name="forum_category_name" label="New forum category name" :value="$forumCategoryName" info="This creates a linked forum category for course participants." />
                    </div>
                    <x-ui.checkbox class="pt-7" name="live_chat_enabled" label="Enable live chat" value="1" :checked="(bool) $liveChatEnabled" />
                    <x-ui.input type="datetime-local" name="starts_at" label="Starts at" :value="$startsAtValue" x-model="startsAtValue" x-on:change="autofillDerivedFields()" />
                    <x-ui.input type="datetime-local" name="ends_at" label="Ends at" :value="$endsAtValue" />
                    <div class="md:col-span-2">
                        <x-ui.media
                            label="Hero image"
                            name="hero_media_name"
                            :value="$heroMediaValue"
                            allow_uploads="true"
                        />
                    </div>
                    <div class="md:col-span-2 rounded-2xl border border-gray-200 bg-gray-50 p-4" x-data="classroomScheduleEditor(@js($broadcastSchedule))" x-init="init()">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-semibold uppercase tracking-wide text-gray-500">Live stream schedule</h3>
                            </div>
                            <x-ui.button type="button" color="primary-outline" x-on:click="addSession()">Add session</x-ui.button>
                        </div>
                        <input type="hidden" name="broadcast_sessions_json" x-ref="output">
                        <div class="mt-4 overflow-hidden rounded-2xl border border-gray-200 bg-white">
                            <div class="hidden md:grid md:grid-cols-[4rem_minmax(0,1.2fr)_minmax(0,1.2fr)_auto] border-b border-gray-200 bg-gray-50 text-xs font-semibold uppercase tracking-wide text-gray-500">
                                <div class="px-4 py-3">#</div>
                                <div class="px-4 py-3">Starts at</div>
                                <div class="px-4 py-3">Ends at</div>
                                <div class="px-4 py-3 text-right">Action</div>
                            </div>
                            <template x-for="(session, index) in sessions" :key="session.key">
                                <div class="border-b border-gray-200 last:border-b-0">
                                    <div class="grid gap-3 px-4 py-4 md:grid-cols-[4rem_minmax(0,1.2fr)_minmax(0,1.2fr)_auto] md:items-end">
                                        <div class="flex items-center gap-2 text-sm font-semibold text-gray-500 md:justify-center md:pt-0 h-full">
                                            <span class="md:hidden text-xs uppercase tracking-wide text-gray-400">Session</span>
                                            <span x-text="index + 1"></span>
                                        </div>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 md:hidden">Starts at</span>
                                            <input type="datetime-local" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" x-model="session.starts_at" x-on:input="syncSessionStart(index)">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500 md:hidden">Ends at</span>
                                            <input type="datetime-local" class="w-full rounded-xl border border-gray-300 px-3 py-2 text-sm" x-model="session.ends_at" x-on:input="syncSessionEnd(index)">
                                        </label>
                                        <div class="flex items-center md:justify-end h-full">
                                            <a href="#" class="hover:text-red-600" x-data x-on:click.prevent="removeSession(index)"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <div x-show="sessions.length === 0" class="px-4 py-6 text-sm text-gray-600">
                                No live broadcasts scheduled yet.
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-2">
                        <x-ui.input type="textarea" name="summary" label="Summary" :value="$summary" info="Short description shown on the course page." />
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm md:p-6">
                <h2 class="text-xl font-semibold text-gray-900">Course notes</h2>
                <div class="mt-4">
                    <x-ui.editor
                        name="instructions_html"
                        :value="$instructionsHtml"
                    />
                </div>
            </section>

            <section
                class="rounded-3xl border border-gray-200 bg-white p-5 shadow-sm md:p-6"
                x-data="classroomEnrolmentEditor({
                    availableUsers: @js($availableUsers),
                    studentIdentifiers: @js($studentValue),
                    paidStudentUserIds: @js($paidStudentUserIds),
                })"
                x-init="init()"
            >
                <h2 class="text-xl font-semibold text-gray-900">Enrolments</h2>

                <input type="hidden" name="teacher_identifiers" value="{{ $teacherValue }}">
                <input type="hidden" name="student_identifiers" x-ref="studentIdentifiers">

                <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-end">
                    <div class="relative flex-1" x-on:click.away="closeSearch()">
                        <label for="student-search" class="block text-sm font-medium text-gray-700">Add student</label>
                        <input
                            id="student-search"
                            type="text"
                            class="mt-1 block w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 focus:border-primary-color focus:outline-none focus:ring-0"
                            placeholder="Search by name, username, or email"
                            x-model="searchTerm"
                            x-on:focus="openSearch()"
                            x-on:input="refreshSuggestions()"
                            x-on:keydown.arrow-down.prevent="moveSuggestion(1)"
                            x-on:keydown.arrow-up.prevent="moveSuggestion(-1)"
                            x-on:keydown.enter.prevent="addHighlightedSuggestion()"
                            x-on:keydown.escape.prevent="closeSearch()"
                        >
                        <div
                            x-show="searchOpen"
                            x-cloak
                            class="absolute z-30 mt-2 w-full overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-lg"
                        >
                            <template x-if="filteredUsers.length > 0">
                                <div class="max-h-72 overflow-auto py-1">
                                    <template x-for="(user, index) in filteredUsers" :key="user.id">
                                        <button
                                            type="button"
                                            class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm transition"
                                            :class="index === highlightedIndex ? 'bg-sky-50 text-sky-900' : 'hover:bg-gray-50 text-gray-800'"
                                            x-on:mouseenter="highlightedIndex = index"
                                            x-on:mousedown.prevent="addUser(user)"
                                        >
                                            <span class="min-w-0">
                                                <span class="block truncate font-medium" x-text="user.label"></span>
                                                <span class="block truncate text-xs text-gray-500" x-text="user.identifier"></span>
                                            </span>
                                            <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-1 text-[11px] font-semibold uppercase tracking-wide text-gray-500" x-text="isPaidUser(user) ? 'Paid' : 'Manual'"></span>
                                        </button>
                                    </template>
                                </div>
                            </template>
                            <template x-if="filteredUsers.length === 0">
                                <div class="px-4 py-3 text-sm text-gray-500">No matching users.</div>
                            </template>
                        </div>
                    </div>
                    <div class="lg:w-auto">
                        <x-ui.button type="button" color="primary-outline" x-on:click="addHighlightedSuggestion()">Add student</x-ui.button>
                    </div>
                </div>

                <div class="mt-5 overflow-hidden rounded-2xl border border-gray-200 bg-gray-50">
                    <template x-if="students.length === 0">
                        <div class="px-4 py-5 text-sm text-gray-600">No students added yet.</div>
                    </template>
                    <template x-for="(student, index) in students" :key="student.key">
                        <div class="flex items-center justify-between gap-4 border-t border-gray-200 px-4 py-3 first:border-t-0">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="truncate font-semibold text-gray-900" x-text="student.label"></span>
                                    <span class="rounded-full px-2 py-1 text-[11px] font-semibold uppercase tracking-wide" :class="student.isPaid ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'" x-text="student.sourceLabel"></span>
                                </div>
                                <div class="mt-1 truncate text-xs text-gray-500" x-text="student.identifier"></div>
                            </div>
                            <x-ui.button type="button" color="danger-outline" x-on:click="removeStudent(index)">Remove</x-ui.button>
                        </div>
                    </template>
                </div>
            </section>

            <div class="flex flex-wrap justify-between gap-3">
                <div class="flex flex-wrap gap-3">
                    <x-ui.button href="{{ route('admin.course.index') }}" color="secondary">Back</x-ui.button>
                    @if($isEditing)
                        <x-ui.button href="{{ route('admin.course.duplicate', $classSession) }}" color="primary-outline">Duplicate</x-ui.button>
                    @endif
                </div>
                <div class="flex flex-wrap gap-3">
                    @if($isEditing && $studentEnrolmentCount > 0)
                        <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                            <div class="font-semibold">Deleting this course will remove student access.</div>
                            <div class="mt-1">
                                {{ $studentEnrolmentCount }} Enroled student{{ $studentEnrolmentCount === 1 ? '' : 's' }} will lose access if you delete this course.
                            </div>
                            @if($paidStudentEnrolmentCount > 0)
                                <div class="mt-1">
                                    {{ $paidStudentEnrolmentCount }} of those students paid through a workshop purchase. Refunds are not automatic.
                                </div>
                            @endif
                            @if($classSession->forum_category_id)
                                <div class="mt-1">
                                    The linked forum category will remain unless it is deleted separately.
                                </div>
                            @endif
                        </div>
                    @endif
                    @if($isEditing)
                        <x-ui.button type="button" color="danger" x-data x-on:click.prevent="SM.confirmDelete('{{ csrf_token() }}', 'Delete course?', @js($deleteWarningHtml), '{{ route('admin.course.destroy', $classSession) }}')">Delete</x-ui.button>
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

    function classroomEnrolmentEditor(initialState) {
        return {
            searchTerm: '',
            searchOpen: false,
            highlightedIndex: -1,
            availableUsers: Array.isArray(initialState?.availableUsers)
                ? initialState.availableUsers.map((user) => ({
                    id: String(user?.id || ''),
                    identifier: String(user?.identifier || '').trim(),
                    label: String(user?.label || '').trim(),
                    username: String(user?.username || '').trim(),
                    email: String(user?.email || '').trim(),
                })).filter((user) => user.id !== '')
                : [],
            paidStudentUserIds: new Set(Array.isArray(initialState?.paidStudentUserIds)
                ? initialState.paidStudentUserIds.map((userId) => String(userId || '').trim()).filter(Boolean)
                : []),
            students: [],
            studentLookup: new Map(),
            init() {
                this.availableUsers.forEach((user) => {
                    const identifiers = [
                        user.id,
                        user.identifier,
                        user.username,
                        user.email,
                    ].map((value) => String(value || '').trim()).filter(Boolean);

                    identifiers.forEach((identifier) => {
                        this.studentLookup.set(this.lookupKey(identifier), user);
                    });
                });

                this.students = this.parseStudents(String(initialState?.studentIdentifiers || ''));
                this.syncHidden();
            },
            lookupKey(value) {
                return String(value || '').trim().toLowerCase();
            },
            parseStudents(value) {
                return String(value || '')
                    .split(/[\r\n,]+/)
                    .map((identifier) => String(identifier || '').trim())
                    .filter(Boolean)
                    .reduce((students, identifier) => {
                        const user = this.resolveUser(identifier);
                        const entry = this.createStudentEntry(user, identifier);
                        if (! students.some((student) => student.identifierKey === entry.identifierKey)) {
                            students.push(entry);
                        }
                        return students;
                    }, []);
            },
            resolveUser(identifier) {
                return this.studentLookup.get(this.lookupKey(identifier)) || this.availableUsers.find((user) => this.lookupKey(user.identifier) === this.lookupKey(identifier)) || null;
            },
            createStudentEntry(user, fallbackIdentifier = '') {
                const identifier = String(user?.identifier || fallbackIdentifier || user?.id || '').trim();
                const label = String(user?.label || identifier || 'Student').trim();
                const userId = String(user?.id || '').trim();
                const isPaid = this.isPaidUser(user);

                return {
                    key: `${userId || identifier}`,
                    identifierKey: this.lookupKey(identifier || userId),
                    identifier,
                    label,
                    userId,
                    isPaid,
                    sourceLabel: isPaid ? 'Paid via workshop purchase' : 'Added by admin',
                };
            },
            isPaidUser(user) {
                return Boolean(user?.id && this.paidStudentUserIds.has(String(user.id)));
            },
            refreshSuggestions() {
                const needle = this.lookupKey(this.searchTerm);
                const selectedKeys = new Set(this.students.map((student) => student.identifierKey));

                this.filteredUsers = this.availableUsers
                    .filter((user) => {
                        const key = this.lookupKey(user.identifier || user.id);
                        if (selectedKeys.has(key)) {
                            return false;
                        }

                        if (needle === '') {
                            return true;
                        }

                        return [
                            user.label,
                            user.identifier,
                            user.username,
                            user.email,
                        ].some((value) => this.lookupKey(value).includes(needle));
                    })
                    .slice(0, 8);

                this.highlightedIndex = this.filteredUsers.length > 0 ? 0 : -1;
                this.searchOpen = true;
            },
            filteredUsers: [],
            openSearch() {
                this.searchOpen = true;
                this.refreshSuggestions();
            },
            closeSearch() {
                this.searchOpen = false;
                this.highlightedIndex = -1;
            },
            moveSuggestion(step) {
                if (!this.searchOpen) {
                    this.openSearch();
                    return;
                }

                const total = this.filteredUsers.length;
                if (total === 0) {
                    return;
                }

                const nextIndex = this.highlightedIndex < 0 ? 0 : (this.highlightedIndex + step + total) % total;
                this.highlightedIndex = nextIndex;
            },
            addUser(user) {
                const resolvedUser = user || this.filteredUsers[this.highlightedIndex] || this.filteredUsers[0] || this.resolveUser(this.searchTerm);
                if (!resolvedUser) {
                    return;
                }

                const entry = this.createStudentEntry(resolvedUser);
                if (this.students.some((student) => student.identifierKey === entry.identifierKey)) {
                    this.searchTerm = '';
                    this.closeSearch();
                    return;
                }

                this.students.push(entry);
                this.searchTerm = '';
                this.syncHidden();
                this.closeSearch();
            },
            addHighlightedSuggestion() {
                this.addUser();
            },
            removeStudent(index) {
                this.students.splice(index, 1);
                this.syncHidden();
            },
            syncHidden() {
                if (this.$refs.studentIdentifiers) {
                    this.$refs.studentIdentifiers.value = this.students
                        .map((student) => String(student.identifier || '').trim())
                        .filter(Boolean)
                        .join('\n');
                }
            },
        };
    }

    function classroomScheduleEditor(initialSessions) {
        return {
            sessions: [],
            init() {
                this.sessions = Array.isArray(initialSessions)
                    ? initialSessions.map((session, index) => this.createSession(session, index))
                    : [];
                this.sync();
            },
            createSession(session, index = 0) {
                const startsAt = this.normalizeDateTimeLocal(session?.starts_at);
                const endsAt = this.normalizeDateTimeLocal(session?.ends_at);
                const autoEnd = startsAt !== '' && (endsAt === '' || endsAt === this.addHours(startsAt, 1));
                const uniqueSuffix = `${Date.now()}-${Math.random().toString(36).slice(2, 8)}`;

                return {
                    key: `session-${index}-${uniqueSuffix}`,
                    starts_at: startsAt,
                    ends_at: endsAt !== '' ? endsAt : (autoEnd && startsAt !== '' ? this.addHours(startsAt, 1) : ''),
                    autoEnd,
                };
            },
            normalizeDateTimeLocal(value) {
                const raw = String(value || '').trim();
                if (raw === '') {
                    return '';
                }

                const match = raw.match(/^(\d{4}-\d{2}-\d{2}T\d{2}:\d{2})/);
                return match ? match[1] : raw;
            },
            addHours(value, hours = 1) {
                const normalized = this.normalizeDateTimeLocal(value);
                if (normalized === '') {
                    return '';
                }

                const date = new Date(normalized);
                if (Number.isNaN(date.getTime())) {
                    return '';
                }

                date.setHours(date.getHours() + hours);

                const pad = (number) => String(number).padStart(2, '0');
                return [
                    date.getFullYear(),
                    '-',
                    pad(date.getMonth() + 1),
                    '-',
                    pad(date.getDate()),
                    'T',
                    pad(date.getHours()),
                    ':',
                    pad(date.getMinutes()),
                ].join('');
            },
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
                this.sessions.push(this.createSession({}, this.sessions.length));
                this.sync();
            },
            removeSession(index) {
                this.sessions.splice(index, 1);
                this.sync();
            },
            syncSessionStart(index) {
                const session = this.sessions[index];
                if (!session) {
                    return;
                }

                session.starts_at = this.normalizeDateTimeLocal(session.starts_at);

                if (session.starts_at === '') {
                    if (session.autoEnd || String(session.ends_at || '').trim() === '') {
                        session.ends_at = '';
                    }
                } else if (session.autoEnd || String(session.ends_at || '').trim() === '') {
                    session.ends_at = this.addHours(session.starts_at, 1);
                    session.autoEnd = true;
                }

                this.sync();
            },
            syncSessionEnd(index) {
                const session = this.sessions[index];
                if (!session) {
                    return;
                }

                session.ends_at = this.normalizeDateTimeLocal(session.ends_at);
                session.autoEnd = session.starts_at !== '' && session.ends_at === this.addHours(session.starts_at, 1);
                this.sync();
            },
        };
    }
</script>
