<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Models\ClassEnrolment;
use App\Models\ClassSession;
use App\Models\ForumTopic;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassroomController extends Controller
{
    public function index(Request $request): View
    {
        /** @var User $user */
        $user = $request->user();
        $now = now();
        $forumUnreadCountByCategoryId = $user ? ForumTopic::unreadCountMapForUser($user) : [];

        $classSessions = ClassSession::query()
            ->with(['forumCategory', 'createdBy'])
            ->withCount([
                'enrolments',
                'enrolments as teacher_count' => fn ($query) => $query->where('role', ClassEnrolment::ROLE_TEACHER),
                'enrolments as student_count' => fn ($query) => $query->where('role', ClassEnrolment::ROLE_STUDENT),
                'helpRequests as pending_help_request_count' => fn ($query) => $query->where('status', 'pending'),
                'helpRequests as active_help_request_count' => fn ($query) => $query->where('status', 'approved'),
            ])
            ->orderByDesc('starts_at')
            ->orderByDesc('created_at')
            ->get()
            ->filter(function (ClassSession $classSession) use ($user): bool {
                return $classSession->canJoin($user) || $classSession->canManage($user);
            })
            ->map(function (ClassSession $classSession) use ($user, $now, $forumUnreadCountByCategoryId): array {
                $role = $classSession->roleForUser($user);
                $status = $this->statusForSession($classSession, $now);
                $forumCategoryId = (string) ($classSession->forum_category_id ?? '');

                return [
                    'classSession' => $classSession,
                    'role' => $role,
                    'status' => $status['key'],
                    'statusLabel' => $status['label'],
                    'statusClass' => $status['class'],
                    'openUrl' => route('class.show', $classSession),
                    'badgeLabel' => $role === ClassEnrolment::ROLE_TEACHER ? 'Teacher' : 'Student',
                    'forumUnreadCount' => (int) ($forumUnreadCountByCategoryId[$forumCategoryId] ?? 0),
                ];
            });

        return view('account.classrooms.index', [
            'classSessions' => $classSessions,
            'hasClassrooms' => $classSessions->isNotEmpty(),
            'currentCount' => $classSessions->where('status', 'current')->count(),
            'upcomingCount' => $classSessions->where('status', 'upcoming')->count(),
            'pastCount' => $classSessions->where('status', 'past')->count(),
        ]);
    }

    /**
     * @return array{key: string, label: string, class: string}
     */
    private function statusForSession(ClassSession $classSession, CarbonInterface $now): array
    {
        $startsAt = $classSession->starts_at;
        $endsAt = $classSession->ends_at;

        if ($startsAt && $startsAt->isFuture()) {
            return [
                'key' => 'upcoming',
                'label' => 'Upcoming',
                'class' => 'border-sky-200 bg-sky-50 text-sky-800',
            ];
        }

        if ($endsAt && $endsAt->isPast()) {
            return [
                'key' => 'past',
                'label' => 'Past',
                'class' => 'border-slate-200 bg-slate-100 text-slate-700',
            ];
        }

        if ($startsAt && $startsAt->lessThanOrEqualTo($now) && (! $endsAt || $endsAt->greaterThanOrEqualTo($now))) {
            return [
                'key' => 'current',
                'label' => 'Live',
                'class' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            ];
        }

        return [
            'key' => 'upcoming',
            'label' => 'Available',
            'class' => 'border-slate-200 bg-slate-50 text-slate-700',
        ];
    }
}
