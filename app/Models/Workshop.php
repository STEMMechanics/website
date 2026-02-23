<?php

namespace App\Models;

use App\Helpers;
use App\Traits\HasFiles;
use App\Traits\Slug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'max_tickets',
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
        'max_tickets' => 'integer',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hero()
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

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

        return trim((string) ($this->location?->name ?? '')) ?: '-';
    }

    public function getLocationDisplay(bool $includeAddress = true): string
    {
        $locationName = $this->getLocationName();
        if (! $includeAddress || $locationName === 'Online') {
            return $locationName;
        }

        $address = trim((string) ($this->location?->address ?? ''));
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
        if ($this->isPrivate() && $this->status === 'open') {
            return 'private';
        }

        return (string) $this->status;
    }

    public function matchesPrivateAccessCode(?string $code): bool
    {
        if (! $this->requiresPrivateAccessCode()) {
            return true;
        }

        $actual = trim((string) ($this->private_code ?? ''));
        $provided = trim((string) ($code ?? ''));

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
