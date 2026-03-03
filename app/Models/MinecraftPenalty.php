<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftPenalty extends Model
{
    use HasFactory;

    public const TYPE_BAN = 'ban';
    public const TYPE_KICK = 'kick';
    public const TYPE_MUTE = 'mute';

    public const TYPES = [
        self::TYPE_BAN,
        self::TYPE_KICK,
        self::TYPE_MUTE,
    ];

    protected $fillable = [
        'minecraft_account_id',
        'external_id',
        'uuid',
        'username',
        'type',
        'reason',
        'duration_seconds',
        'started_at',
        'ends_at',
        'is_permanent',
        'by_uuid',
        'by_username',
        'lifted_at',
        'lifted_by_uuid',
        'lifted_by_username',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_permanent' => 'boolean',
        'lifted_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class, 'minecraft_account_id');
    }

    public function isActiveRestriction(): bool
    {
        if (! in_array($this->type, [self::TYPE_BAN, self::TYPE_MUTE], true)) {
            return false;
        }

        if ($this->lifted_at !== null) {
            return false;
        }

        if ($this->is_permanent) {
            return true;
        }

        return $this->ends_at !== null && $this->ends_at->isFuture();
    }
}
