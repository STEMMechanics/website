<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Support\Carbon;
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
        'term_number',
        'slug',
        'room_name',
        'access_group_slug',
        'forum_category_id',
        'workshop_id',
        'created_by_user_id',
        'duplicated_from_class_session_id',
        'summary',
        'instructions_html',
        'live_chat_enabled',
        'starts_at',
        'ends_at',
        'broadcast_sessions_json',
        'live_broadcast_started_at',
        'live_broadcast_ended_at',
        'live_broadcast_started_by_user_id',
        'live_broadcast_ended_by_user_id',
    ];

    protected $casts = [
        'term_number' => 'integer',
        'live_chat_enabled' => 'boolean',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'broadcast_sessions_json' => 'array',
        'live_broadcast_started_at' => 'datetime',
        'live_broadcast_ended_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $classSession): void {
            $slug = trim((string) $classSession->slug);
            if ($slug === '') {
                $slug = $classSession->defaultSlug();
            } else {
                $slug = Str::slug($slug);
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

            $classSession->term_number = $classSession->currentTermNumber();

            $classSession->access_group_slug = $slug;

            if (is_array($classSession->broadcast_sessions_json)) {
                $classSession->broadcast_sessions_json = collect($classSession->broadcast_sessions_json)
                    ->map(function ($entry): array {
                        return [
                            'starts_at' => self::normalizeDateTimeLocalValue(data_get($entry, 'starts_at', null)),
                            'ends_at' => self::normalizeDateTimeLocalValue(data_get($entry, 'ends_at', null)),
                            'label' => trim((string) data_get($entry, 'label', '')),
                        ];
                    })
                    ->filter(fn (array $entry): bool => $entry['starts_at'] !== null || $entry['ends_at'] !== null || $entry['label'] !== '')
                    ->values()
                    ->all();
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

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
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

    /**
     * @return array<int, array{starts_at: ?string, ends_at: ?string, label: string}>
     */
    public function broadcastSchedule(): array
    {
        $schedule = $this->broadcast_sessions_json;
        if (! is_array($schedule)) {
            return [];
        }

        return collect($schedule)
            ->map(function ($entry): array {
                $startsAt = self::normalizeDateTimeLocalValue(data_get($entry, 'starts_at', null));
                $endsAt = self::normalizeDateTimeLocalValue(data_get($entry, 'ends_at', null));
                $label = trim((string) data_get($entry, 'label', ''));

                return [
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'label' => $label,
                ];
            })
            ->filter(fn (array $entry): bool => $entry['starts_at'] !== null || $entry['ends_at'] !== null || $entry['label'] !== '')
            ->values()
            ->all();
    }

    private static function normalizeDateTimeLocalValue(mixed $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $value) === 1) {
            return $value;
        }

        foreach ([
            'd/m/Y, H:i',
            'd/m/Y, h:i a',
            'd/m/Y, g:i a',
            'j/m/Y, H:i',
            'j/m/Y, h:i a',
            'j/m/Y, g:i a',
            'd/m/Y H:i',
            'j/m/Y H:i',
        ] as $format) {
            try {
                return Carbon::createFromFormat($format, $value)->format('Y-m-d\TH:i');
            } catch (\Throwable) {
                // Try the next known classroom format.
            }
        }

        try {
            return Carbon::parse($value)->format('Y-m-d\TH:i');
        } catch (\Throwable) {
            return $value;
        }
    }

    public function defaultSlug(): string
    {
        $titleSlug = Str::slug((string) $this->title);
        $titleSlug = $titleSlug !== '' ? $titleSlug : 'class';
        $termNumber = max(1, (int) ($this->term_number ?? $this->currentTermNumber()));
        $year = $this->starts_at?->year ?? now()->year;

        return 'class-'.$titleSlug.'-term'.$termNumber.'-'.$year;
    }

    public function currentTermNumber(): int
    {
        $reference = $this->starts_at ?? now();
        $month = (int) $reference->format('n');

        return max(1, (int) ceil($month / 3));
    }

    public function isLiveBroadcastOpen(): bool
    {
        return $this->live_broadcast_started_at !== null && $this->live_broadcast_ended_at === null;
    }
}
