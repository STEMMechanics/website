<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ClassSession extends Model
{
    use HasFactory;
    use UUID;

    protected $fillable = [
        'title',
        'slug',
        'room_name',
        'access_group_slug',
        'forum_category_id',
        'created_by_user_id',
        'duplicated_from_class_session_id',
        'summary',
        'instructions_html',
        'live_chat_enabled',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'live_chat_enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $classSession): void {
            $slug = trim((string) $classSession->slug);
            if ($slug === '') {
                $slug = Str::slug((string) $classSession->title);
            }

            if ($slug === '') {
                $slug = 'class-session';
            }

            $classSession->slug = $slug;

            if (trim((string) $classSession->room_name) === '') {
                $classSession->room_name = $slug;
            }

            if (trim((string) ($classSession->summary ?? '')) === '') {
                $classSession->summary = null;
            }

            if (trim((string) ($classSession->access_group_slug ?? '')) === '') {
                $classSession->access_group_slug = null;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function forumCategory(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function duplicatedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicated_from_class_session_id');
    }

    /**
     * @return HasMany<ClassEnrolment, $this>
     */
    public function enrolments(): HasMany
    {
        return $this->hasMany(ClassEnrolment::class)->orderBy('role')->orderBy('created_at');
    }

    /**
     * @return HasMany<ClassHelpRequest, $this>
     */
    public function helpRequests(): HasMany
    {
        return $this->hasMany(ClassHelpRequest::class)->orderByDesc('created_at');
    }

    /**
     * @return HasMany<ClassChatMessage, $this>
     */
    public function chatMessages(): HasMany
    {
        return $this->hasMany(ClassChatMessage::class)->orderBy('created_at');
    }

    public function teacherEnrolment(): HasOne
    {
        return $this->hasOne(ClassEnrolment::class)->where('role', ClassEnrolment::ROLE_TEACHER);
    }

    public function studentEnrolment(): HasOne
    {
        return $this->hasOne(ClassEnrolment::class)->where('role', ClassEnrolment::ROLE_STUDENT);
    }

    public function roleForUser(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        if ($this->enrolments()
            ->where('user_id', (string) $user->id)
            ->where('role', ClassEnrolment::ROLE_TEACHER)
            ->exists()) {
            return ClassEnrolment::ROLE_TEACHER;
        }

        if ($this->enrolments()
            ->where('user_id', (string) $user->id)
            ->where('role', ClassEnrolment::ROLE_STUDENT)
            ->exists()) {
            return ClassEnrolment::ROLE_STUDENT;
        }

        $groupSlug = trim((string) ($this->access_group_slug ?? ''));
        if ($groupSlug !== '' && $user->hasGroup($groupSlug)) {
            return ClassEnrolment::ROLE_STUDENT;
        }

        if ($user->isAdmin()) {
            return ClassEnrolment::ROLE_STUDENT;
        }

        return null;
    }

    public function canJoin(?User $user): bool
    {
        return $this->roleForUser($user) !== null;
    }

    public function canManage(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->hasGroup('minecraft-org')) {
            return true;
        }

        return $this->enrolments()
            ->where('user_id', (string) $user->id)
            ->where('role', ClassEnrolment::ROLE_TEACHER)
            ->exists();
    }

    public function pendingHelpRequests(): HasMany
    {
        return $this->helpRequests()->where('status', ClassHelpRequest::STATUS_PENDING);
    }

    public function activeHelpRequest(): ?ClassHelpRequest
    {
        return $this->helpRequests()
            ->where('status', ClassHelpRequest::STATUS_APPROVED)
            ->orderByDesc('approved_at')
            ->first();
    }

    public function helpRequestForUser(User $user): ?ClassHelpRequest
    {
        return $this->helpRequests()
            ->where('user_id', (string) $user->id)
            ->whereIn('status', [ClassHelpRequest::STATUS_PENDING, ClassHelpRequest::STATUS_APPROVED])
            ->orderByDesc('created_at')
            ->first();
    }

    public function participants(): Collection
    {
        return $this->enrolments()
            ->with('user')
            ->get()
            ->map(fn (ClassEnrolment $enrolment): array => [
                'user_id' => (string) $enrolment->user_id,
                'username' => (string) ($enrolment->user?->username ?? $enrolment->user?->getName() ?? ''),
                'role' => (string) $enrolment->role,
            ]);
    }
}
