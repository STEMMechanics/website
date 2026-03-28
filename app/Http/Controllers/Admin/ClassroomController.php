<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim((string) $request->query('search', ''));

        $query = ClassSession::query()
            ->with(['createdBy', 'forumCategory'])
            ->withCount([
                'enrolments',
                'enrolments as teacher_count' => fn (Builder $builder) => $builder->where('role', ClassEnrolment::ROLE_TEACHER),
                'enrolments as student_count' => fn (Builder $builder) => $builder->where('role', ClassEnrolment::ROLE_STUDENT),
                'helpRequests as pending_help_request_count' => fn (Builder $builder) => $builder->where('status', 'pending'),
                'helpRequests as active_help_request_count' => fn (Builder $builder) => $builder->where('status', 'approved'),
            ]);

        if ($search !== '') {
            $query->where(function (Builder $builder) use ($search): void {
                $builder->where('title', 'like', '%'.$search.'%')
                    ->orWhere('slug', 'like', '%'.$search.'%')
                    ->orWhere('room_name', 'like', '%'.$search.'%')
                    ->orWhere('access_group_slug', 'like', '%'.$search.'%');
            });
        }

        $classSessions = $query
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->paginate(15)
            ->onEachSide(1);

        return view('admin.classroom.index', [
            'classSessions' => $classSessions,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $sourceClassSession = $this->resolveDuplicateSource($request);

        return view('admin.classroom.edit', $this->formViewData(
            classSession: null,
            sourceClassSession: $sourceClassSession
        ));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $this->validateClassSession($request);
        $sourceClassSession = $this->resolveDuplicateSource($request);

        $classSession = DB::transaction(function () use ($validated, $request, $sourceClassSession): ClassSession {
            $classSession = new ClassSession();
            $classSession->fill($this->classSessionAttributes($validated, $request));
            $classSession->created_by_user_id = (string) $request->user()->id;

            if ($sourceClassSession instanceof ClassSession) {
                $classSession->duplicated_from_class_session_id = $sourceClassSession->id;
            }

            $classSession->save();
            $this->syncEnrolments($classSession, $validated);

            return $classSession;
        });

        session()->flash('message', 'Classroom has been created.');
        session()->flash('message-title', 'Classroom created');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.classroom.edit', $classSession);
    }

    public function edit(ClassSession $classSession): View
    {
        $classSession->loadMissing(['createdBy', 'forumCategory', 'duplicatedFrom', 'enrolments.user']);

        return view('admin.classroom.edit', $this->formViewData(
            classSession: $classSession
        ));
    }

    public function update(Request $request, ClassSession $classSession): RedirectResponse
    {
        $validated = $this->validateClassSession($request, $classSession);

        DB::transaction(function () use ($validated, $request, $classSession): void {
            $classSession->fill($this->classSessionAttributes($validated, $request));
            $classSession->save();
            $this->syncEnrolments($classSession, $validated);
        });

        session()->flash('message', 'Classroom updated.');
        session()->flash('message-title', $classSession->title);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.classroom.edit', $classSession);
    }

    public function duplicate(ClassSession $classSession): RedirectResponse
    {
        $classSession->loadMissing(['enrolments.user']);

        $duplicate = DB::transaction(function () use ($classSession): ClassSession {
            $duplicate = $classSession->replicate([
                'id',
                'slug',
                'room_name',
                'created_at',
                'updated_at',
            ]);
            $duplicate->title = $classSession->title.' (copy)';
            $duplicate->slug = null;
            $duplicate->room_name = null;
            $duplicate->created_by_user_id = (string) auth()->id();
            $duplicate->duplicated_from_class_session_id = $classSession->id;
            $duplicate->save();

            $this->syncEnrolmentsFromSource($duplicate, $classSession);

            return $duplicate;
        });

        session()->flash('message', 'Classroom duplicated.');
        session()->flash('message-title', 'Classroom duplicated');
        session()->flash('message-type', 'success');

        return redirect()->route('admin.classroom.edit', $duplicate);
    }

    public function destroy(ClassSession $classSession): RedirectResponse
    {
        $title = $classSession->title;
        $classSession->delete();

        session()->flash('message', 'Classroom deleted.');
        session()->flash('message-title', $title);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.classroom.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function formViewData(?ClassSession $classSession, ?ClassSession $sourceClassSession = null): array
    {
        $session = $classSession ?? $this->defaultClassSessionFromSource($sourceClassSession);
        $session?->loadMissing(['createdBy', 'forumCategory', 'duplicatedFrom', 'enrolments.user']);
        $baseSession = $session instanceof ClassSession && $session->exists
            ? $session
            : $sourceClassSession;

        return [
            'classSession' => $session,
            'sourceClassSession' => $sourceClassSession,
            'forumCategories' => ForumCategory::query()->orderBy('name')->get(),
            'groupSuggestions' => $this->groupSuggestions(),
            'teacherIdentifiers' => $this->identifiersForRole($baseSession, ClassEnrolment::ROLE_TEACHER),
            'studentIdentifiers' => $this->identifiersForRole($baseSession, ClassEnrolment::ROLE_STUDENT),
        ];
    }

    private function defaultClassSessionFromSource(?ClassSession $sourceClassSession): ?ClassSession
    {
        if (! $sourceClassSession instanceof ClassSession) {
            return null;
        }

        $duplicate = $sourceClassSession->replicate([
            'id',
            'slug',
            'room_name',
            'created_at',
            'updated_at',
        ]);
        $duplicate->title = $sourceClassSession->title.' (copy)';
        $duplicate->slug = '';
        $duplicate->room_name = '';
        $duplicate->created_by_user_id = null;
        $duplicate->duplicated_from_class_session_id = $sourceClassSession->id;

        return $duplicate;
    }

    private function resolveDuplicateSource(Request $request): ?ClassSession
    {
        $duplicateFrom = trim((string) $request->query('duplicate_from', ''));
        if ($duplicateFrom === '') {
            return null;
        }

        return ClassSession::query()
            ->with(['enrolments.user'])
            ->where('id', $duplicateFrom)
            ->orWhere('slug', $duplicateFrom)
            ->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function validateClassSession(Request $request, ?ClassSession $classSession = null): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('class_sessions', 'slug')->ignore($classSession?->id),
            ],
            'room_name' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('class_sessions', 'room_name')->ignore($classSession?->id),
            ],
            'access_group_slug' => ['nullable', 'string', 'max:80'],
            'forum_category_id' => ['nullable', 'uuid', 'exists:forum_categories,id'],
            'summary' => ['nullable', 'string', 'max:255'],
            'instructions_html' => ['nullable', 'string'],
            'live_chat_enabled' => ['nullable', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'teacher_identifiers' => ['nullable', 'string', 'max:5000'],
            'student_identifiers' => ['nullable', 'string', 'max:5000'],
        ]);

        return [
            'title' => trim((string) $validated['title']),
            'slug' => trim((string) ($validated['slug'] ?? '')),
            'room_name' => trim((string) ($validated['room_name'] ?? '')),
            'access_group_slug' => UserGroup::normalizeSlug((string) ($validated['access_group_slug'] ?? '')),
            'forum_category_id' => trim((string) ($validated['forum_category_id'] ?? '')),
            'summary' => trim((string) ($validated['summary'] ?? '')),
            'instructions_html' => (string) ($validated['instructions_html'] ?? ''),
            'live_chat_enabled' => $request->boolean('live_chat_enabled'),
            'starts_at' => trim((string) ($validated['starts_at'] ?? '')) !== '' ? trim((string) $validated['starts_at']) : null,
            'ends_at' => trim((string) ($validated['ends_at'] ?? '')) !== '' ? trim((string) $validated['ends_at']) : null,
            'teacher_identifiers' => $this->parseIdentifiers((string) ($validated['teacher_identifiers'] ?? '')),
            'student_identifiers' => $this->parseIdentifiers((string) ($validated['student_identifiers'] ?? '')),
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function classSessionAttributes(array $validated, Request $request): array
    {
        return [
            'title' => $validated['title'],
            'slug' => $validated['slug'] !== '' ? $validated['slug'] : null,
            'room_name' => $validated['room_name'] !== '' ? $validated['room_name'] : null,
            'access_group_slug' => $validated['access_group_slug'] !== '' ? $validated['access_group_slug'] : null,
            'forum_category_id' => $validated['forum_category_id'] !== '' ? $validated['forum_category_id'] : null,
            'summary' => $validated['summary'] !== '' ? $validated['summary'] : null,
            'instructions_html' => $validated['instructions_html'] !== '' ? $validated['instructions_html'] : null,
            'live_chat_enabled' => $request->boolean('live_chat_enabled'),
            'starts_at' => $validated['starts_at'] !== null ? $validated['starts_at'] : null,
            'ends_at' => $validated['ends_at'] !== null ? $validated['ends_at'] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function syncEnrolments(ClassSession $classSession, array $validated): void
    {
        $teacherUsers = $this->resolveUsers($validated['teacher_identifiers'], 'teacher_identifiers');
        $studentUsers = $this->resolveUsers($validated['student_identifiers'], 'student_identifiers');

        $classSession->enrolments()->delete();

        foreach ($teacherUsers as $user) {
            $classSession->enrolments()->create([
                'user_id' => $user->id,
                'role' => ClassEnrolment::ROLE_TEACHER,
            ]);
        }

        foreach ($studentUsers as $user) {
            if ($teacherUsers->contains(fn (User $teacher): bool => (string) $teacher->id === (string) $user->id)) {
                continue;
            }

            $classSession->enrolments()->create([
                'user_id' => $user->id,
                'role' => ClassEnrolment::ROLE_STUDENT,
            ]);
        }
    }

    private function syncEnrolmentsFromSource(ClassSession $duplicate, ClassSession $source): void
    {
        $source->loadMissing(['enrolments.user']);

        foreach ($source->enrolments as $enrolment) {
            $duplicate->enrolments()->create([
                'user_id' => $enrolment->user_id,
                'role' => $enrolment->role,
            ]);
        }
    }

    /**
     * @param  list<string>  $identifiers
     * @return Collection<int, User>
     */
    private function resolveUsers(array $identifiers, string $fieldName): Collection
    {
        $users = collect();
        $missing = [];

        foreach ($identifiers as $identifier) {
            $user = $this->resolveUser($identifier);
            if (! $user instanceof User) {
                $missing[] = $identifier;
                continue;
            }

            if (! $users->contains(fn (User $existing): bool => (string) $existing->id === (string) $user->id)) {
                $users->push($user);
            }
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                $fieldName => 'Could not find: '.implode(', ', $missing),
            ]);
        }

        return $users;
    }

    private function resolveUser(string $identifier): ?User
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        return User::query()
            ->whereRaw('LOWER(id) = ?', [strtolower($identifier)])
            ->orWhereRaw('LOWER(username) = ?', [strtolower($identifier)])
            ->orWhereRaw('LOWER(email) = ?', [strtolower($identifier)])
            ->first();
    }

    /**
     * @return list<string>
     */
    private function parseIdentifiers(string $value): array
    {
        return collect(preg_split('/[\r\n,]+/', $value) ?: [])
            ->map(fn (string $identifier): string => trim($identifier))
            ->filter(fn (string $identifier): bool => $identifier !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function groupSuggestions(): array
    {
        return UserGroup::query()
            ->select('slug')
            ->distinct()
            ->orderBy('slug')
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->filter(fn (string $slug) => $slug !== '')
            ->values()
            ->all();
    }

    /**
     * @return list<string>
     */
    private function identifiersForRole(?ClassSession $classSession, string $role): array
    {
        if (! $classSession instanceof ClassSession) {
            return [];
        }

        $classSession->loadMissing(['enrolments.user']);

        return $classSession->enrolments
            ->where('role', $role)
            ->map(function (ClassEnrolment $enrolment): string {
                return trim((string) ($enrolment->user?->username ?: $enrolment->user?->email ?: $enrolment->user_id));
            })
            ->filter(fn (string $identifier): bool => $identifier !== '')
            ->unique()
            ->values()
            ->all();
    }
}
