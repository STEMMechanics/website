<?php

namespace App\Models;

use App\Helpers;
use App\Traits\HasFiles;
use App\Traits\Slug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Workshop extends Model
{
    use HasFactory, HasFiles, Slug;

    protected $fillable = [
        'title',
        'content',
        'starts_at',
        'ends_at',
        'publish_at',
        'closes_at',
        'status',
        'price',
        'ages',
        'registration',
        'registration_data',
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

    public function getTicketTimeRangeLabel(): string
    {
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
