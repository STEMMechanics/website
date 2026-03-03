<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class MinecraftAccount extends Model
{
    use HasFactory;

    public const PLATFORM_JAVA = 'java';
    public const PLATFORM_BEDROCK = 'bedrock';

    public const PLATFORMS = [
        self::PLATFORM_JAVA,
        self::PLATFORM_BEDROCK,
    ];

    protected $fillable = [
        'user_id',
        'platform',
        'uuid',
        'username',
        'is_whitelisted',
        'admin_notes',
        'last_login_at',
        'last_logout_at',
        'last_seen_at',
    ];

    protected $casts = [
        'is_whitelisted' => 'boolean',
        'last_login_at' => 'datetime',
        'last_logout_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(MinecraftSession::class)->orderByDesc('logged_in_at');
    }

    public function penalties(): HasMany
    {
        return $this->hasMany(MinecraftPenalty::class, 'minecraft_account_id')->orderByDesc('started_at');
    }

    public function blacklistEntries(): HasMany
    {
        return $this->hasMany(MinecraftBlacklistEntry::class, 'minecraft_account_id')->orderByDesc('starts_at');
    }

    public function activeBlacklistEntry(): ?MinecraftBlacklistEntry
    {
        $entries = $this->relationLoaded('blacklistEntries')
            ? $this->blacklistEntries
            : $this->blacklistEntries()->get();

        /** @var MinecraftBlacklistEntry|null $active */
        $active = $entries->first(function ($entry): bool {
            return $entry instanceof MinecraftBlacklistEntry && $entry->isActive();
        });

        return $active;
    }

    public function activePenalty(): ?MinecraftPenalty
    {
        $penalties = $this->relationLoaded('penalties')
            ? $this->penalties
            : $this->penalties()->get();

        /** @var MinecraftPenalty|null $active */
        $active = $penalties->first(function ($penalty): bool {
            return $penalty instanceof MinecraftPenalty && $penalty->isActiveRestriction();
        });

        return $active;
    }

    public function statusSummary(): array
    {
        $blacklist = $this->activeBlacklistEntry();
        if ($blacklist) {
            return [
                'label' => $blacklist->is_permanent ? 'Banned permanently' : 'Banned',
                'class' => 'text-red-700',
            ];
        }

        $penalty = $this->activePenalty();
        if ($penalty) {
            if ($penalty->type === MinecraftPenalty::TYPE_BAN) {
                return [
                    'label' => $penalty->is_permanent
                        ? 'Banned permanently'
                        : 'Banned until '.($penalty->ends_at?->format('j M Y g:i a') ?? '-'),
                    'class' => 'text-red-700',
                ];
            }

            if ($penalty->type === MinecraftPenalty::TYPE_MUTE) {
                return [
                    'label' => $penalty->is_permanent
                        ? 'Muted permanently'
                        : 'Muted until '.($penalty->ends_at?->format('j M Y g:i a') ?? '-'),
                    'class' => 'text-amber-700',
                ];
            }
        }

        if (! $this->is_whitelisted) {
            return [
                'label' => 'Not whitelisted',
                'class' => 'text-gray-600',
            ];
        }

        return [
            'label' => 'Whitelisted',
            'class' => 'text-green-700',
        ];
    }

    public function touchSeen(?Carbon $at = null): void
    {
        $timestamp = $at ?? now();
        $this->last_seen_at = $timestamp;
        $this->save();
    }
}
