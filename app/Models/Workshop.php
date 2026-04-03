<?php

namespace App\Models;

use App\Helpers;
use App\Traits\HasFiles;
use App\Traits\Slug;
use App\Models\ClassSession;
use App\Models\ForumCategory;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class Workshop extends Model
{
    use HasFactory, HasFiles, Slug;

    protected $fillable = [
        'title',
        'content',
        'summary',
        'starts_at',
        'ends_at',
        'publish_at',
        'closes_at',
        'status',
        'price',
        'ages',
        'registration',
        'registration_data',
        'class_session_id',
        'classroom_forum_category_id',
        'classroom_sessions_json',
        'private_code',
        'hosted_for',
        'is_private',
        'is_hidden',
        'max_tickets',
        'ticket_group_slug',
        'pick_list_template_id',
        'pick_list_participants',
        'pick_list_checked_item_ids',
        'pick_list_notes',
        'pick_list_canvas_data',
        'pick_list_canvas_thumbnail_path',
        'location_id',
        'user_id',
        'hero_media_name',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'publish_at' => 'datetime',
        'closes_at' => 'datetime',
        'is_private' => 'boolean',
        'is_hidden' => 'boolean',
        'max_tickets' => 'integer',
        'pick_list_participants' => 'integer',
        'pick_list_checked_item_ids' => 'array',
        'classroom_sessions_json' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function hero(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }

    /**
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    /**
     * @return BelongsTo<PickListTemplate, $this>
     */
    public function pickListTemplate(): BelongsTo
    {
        return $this->belongsTo(PickListTemplate::class, 'pick_list_template_id');
    }

    /**
     * @return BelongsTo<ClassSession, $this>
     */
    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class, 'class_session_id');
    }

    /**
     * @return BelongsTo<ForumCategory, $this>
     */
    public function classroomForumCategory(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'classroom_forum_category_id');
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<WorkshopAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(WorkshopAttendance::class);
    }

    /**
     * @return HasMany<WorkshopInterest, $this>
     */
    public function interests(): HasMany
    {
        return $this->hasMany(WorkshopInterest::class);
    }

    public function getLocationName(): string
    {
        $locationId = trim((string) ($this->location_id ?? ''));

        if ($locationId === '') {
            return 'Online';
        }

        return trim((string) ($this->location->name ?? '')) ?: '-';
    }

    public function getLocationDisplay(bool $includeAddress = true): string
    {
        $locationName = $this->getLocationName();
        if (! $includeAddress || $locationName === 'Online') {
            return $locationName;
        }

        $address = trim((string) ($this->location->address ?? ''));
        if ($address === '') {
            return $locationName;
        }

        if ($locationName === '-' || $locationName === '') {
            return $address;
        }

        return $locationName.' - '.$address;
    }

    public function newsletterSummary(int $limit = 180): string
    {
        $summary = trim((string) ($this->summary ?? ''));
        if ($summary !== '') {
            return Str::limit((string) Str::of($summary)->squish(), $limit);
        }

        $content = trim((string) ($this->content ?? ''));
        if ($content === '') {
            return '';
        }

        $content = trim((string) Str::of(strip_tags($content))->squish());

        return Str::limit($content, $limit);
    }

    public function getTicketTimeRangeLabel(): string
    {
        if ($this->usesClassroomRegistration()) {
            if ($this->effectiveScheduleEntries() === []) {
                return 'Anytime';
            }

            $start = $this->effectiveStartsAt();
            $end = $this->effectiveEndsAt();

            if (! $start || ! $end) {
                return 'Anytime';
            }

            return Helpers::createTicketTimeDurationStr(
                $start->toDateTimeString(),
                $end->toDateTimeString()
            );
        }

        if (! $this->starts_at || ! $this->ends_at) {
            return $this->starts_at?->format('M j, Y g:i a') ?? '-';
        }

        return Helpers::createTicketTimeDurationStr(
            $this->starts_at->toDateTimeString(),
            $this->ends_at->toDateTimeString()
        );
    }

    public function requiresPrivateTicketCode(): bool
    {
        return $this->registration === 'tickets'
            && $this->isPrivate()
            && trim((string) ($this->private_code ?? '')) !== '';
    }

    public function requiresPrivateAccessCode(): bool
    {
        return $this->isPrivate()
            && trim((string) ($this->private_code ?? '')) !== '';
    }

    public function usesClassroomRegistration(): bool
    {
        return (string) $this->registration === 'classroom';
    }

    /**
     * @return array<int, array{starts_at: ?string, ends_at: ?string, label: string}>
     */
    public function effectiveScheduleEntries(): array
    {
        return $this->usesClassroomRegistration()
            ? $this->classroomSchedule()
            : [];
    }

    public function effectiveStartsAt(): ?CarbonInterface
    {
        $entries = $this->effectiveScheduleEntries();
        if ($entries !== []) {
            $firstStartsAt = trim((string) ($entries[0]['starts_at'] ?? ''));
            if ($firstStartsAt !== '') {
                try {
                    return Carbon::parse($firstStartsAt);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return $this->starts_at;
    }

    public function effectiveEndsAt(): ?CarbonInterface
    {
        $entries = $this->effectiveScheduleEntries();
        if ($entries !== []) {
            $lastEntry = $entries[array_key_last($entries)] ?? [];
            $lastEndsAt = trim((string) ($lastEntry['ends_at'] ?? ''));
            if ($lastEndsAt !== '') {
                try {
                    return Carbon::parse($lastEndsAt);
                } catch (\Throwable) {
                    return null;
                }
            }

            $lastStartsAt = trim((string) ($lastEntry['starts_at'] ?? ''));
            if ($lastStartsAt !== '') {
                try {
                    return Carbon::parse($lastStartsAt);
                } catch (\Throwable) {
                    return null;
                }
            }
        }

        return $this->ends_at;
    }

    public function courseScheduleFirstStartLabel(): string
    {
        if ($this->usesClassroomRegistration() && $this->effectiveScheduleEntries() === []) {
            return 'Anytime';
        }

        $start = $this->effectiveStartsAt();
        if (! $start) {
            return 'Anytime';
        }

        return $start->format('j/m/Y @ g:i a');
    }

    public function courseScheduleCadenceLabel(): ?string
    {
        $entries = $this->effectiveScheduleEntries();
        $starts = collect($entries)
            ->pluck('starts_at')
            ->filter()
            ->values();

        if ($starts->count() < 2) {
            return null;
        }

        try {
            $first = Carbon::parse((string) $starts[0]);
            $second = Carbon::parse((string) $starts[1]);
        } catch (\Throwable) {
            return null;
        }

        $days = abs($first->diffInDays($second));
        if ($days >= 6 && $days <= 8) {
            return 'weekly';
        }

        if ($days >= 13 && $days <= 15) {
            return 'fortnightly';
        }

        if ($days >= 27 && $days <= 32) {
            return 'monthly';
        }

        return null;
    }

    /**
     * @return array<int, string>
     */
    public function courseScheduleDisplayLines(): array
    {
        $entries = $this->effectiveScheduleEntries();
        if ($entries === []) {
            return ['Anytime'];
        }

        return collect($entries)
            ->map(function (array $entry): string {
                $startsAt = trim((string) ($entry['starts_at'] ?? ''));
                $endsAt = trim((string) ($entry['ends_at'] ?? ''));
                $label = trim((string) $entry['label']);

                if ($startsAt === '') {
                    return $label !== '' ? $label : 'Anytime';
                }

                try {
                    $start = Carbon::parse($startsAt);
                } catch (\Throwable) {
                    return $label !== '' ? $label : $startsAt;
                }

                $line = $start->format('D j M Y g:ia');
                if ($endsAt !== '') {
                    try {
                        $end = Carbon::parse($endsAt);
                        $line .= ' - '.$end->format('g:ia');
                    } catch (\Throwable) {
                        // Ignore malformed end dates and keep the start time.
                    }
                }

                if ($label !== '') {
                    $line .= ' - '.$label;
                }

                return $line;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{starts_at: ?string, ends_at: ?string, label: string}>
     */
    public function classroomSchedule(): array
    {
        if ($this->relationLoaded('classSession') && $this->classSession instanceof ClassSession) {
            $schedule = $this->classSession->broadcastSchedule();
            if ($schedule !== []) {
                return $schedule;
            }

            $classSessionStartsAt = trim((string) ($this->classSession->starts_at?->toDateTimeString() ?? ''));
            $classSessionEndsAt = trim((string) ($this->classSession->ends_at?->toDateTimeString() ?? ''));
            if ($classSessionStartsAt !== '' || $classSessionEndsAt !== '') {
                return [[
                    'starts_at' => $classSessionStartsAt !== '' ? Carbon::parse($classSessionStartsAt)->format('Y-m-d\TH:i') : null,
                    'ends_at' => $classSessionEndsAt !== '' ? Carbon::parse($classSessionEndsAt)->format('Y-m-d\TH:i') : null,
                    'label' => '',
                ]];
            }
        }

        if ($this->classSession instanceof ClassSession) {
            $schedule = $this->classSession->broadcastSchedule();
            if ($schedule !== []) {
                return $schedule;
            }

            $classSessionStartsAt = trim((string) ($this->classSession->starts_at?->toDateTimeString() ?? ''));
            $classSessionEndsAt = trim((string) ($this->classSession->ends_at?->toDateTimeString() ?? ''));
            if ($classSessionStartsAt !== '' || $classSessionEndsAt !== '') {
                return [[
                    'starts_at' => $classSessionStartsAt !== '' ? Carbon::parse($classSessionStartsAt)->format('Y-m-d\TH:i') : null,
                    'ends_at' => $classSessionEndsAt !== '' ? Carbon::parse($classSessionEndsAt)->format('Y-m-d\TH:i') : null,
                    'label' => '',
                ]];
            }
        }

        $schedule = $this->classroom_sessions_json;
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

    public function isPrivate(): bool
    {
        return (bool) ($this->is_private ?? false) || $this->status === 'private';
    }

    public function publicStatus(): string
    {
        if ((bool) ($this->is_hidden ?? false)) {
            return 'hidden';
        }

        if ($this->isPrivate() && $this->status === 'open') {
            return 'private';
        }

        return (string) $this->status;
    }

    public function publicStatusLabel(): string
    {
        $status = $this->publicStatus();

        if ($status === 'scheduled') {
            return 'Opens Soon';
        }

        return ucwords($status);
    }

    public function isPubliclyVisible(): bool
    {
        if ($this->status === 'draft') {
            return false;
        }

        if ((bool) ($this->is_hidden ?? false)) {
            return true;
        }

        if ($this->publish_at === null) {
            return true;
        }

        return $this->publish_at->lte(now());
    }

    public function scopePubliclyVisible(Builder $query): Builder
    {
        return $query
            ->where('status', '!=', 'draft')
            ->where(function (Builder $builder) {
                $builder->whereNull('is_hidden')
                    ->orWhere('is_hidden', false);
            })
            ->where(function (Builder $builder) {
                $builder->whereNull('publish_at')
                    ->orWhere('publish_at', '<=', now());
            });
    }

    public function matchesPrivateAccessCode(?string $code): bool
    {
        if (! $this->requiresPrivateAccessCode()) {
            return true;
        }

        $actual = strtolower(trim((string) ($this->private_code ?? '')));
        $provided = strtolower(trim((string) ($code ?? '')));

        return $provided !== '' && hash_equals($actual, $provided);
    }

    public function matchesPrivateTicketCode(?string $code): bool
    {
        if (! $this->requiresPrivateTicketCode()) {
            return true;
        }

        return $this->matchesPrivateAccessCode($code);
    }
}
