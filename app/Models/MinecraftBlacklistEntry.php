<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftBlacklistEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'minecraft_account_id',
        'uuid',
        'username',
        'reason',
        'starts_at',
        'ends_at',
        'is_permanent',
        'lifted_at',
        'created_by',
        'lifted_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_permanent' => 'boolean',
        'lifted_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class, 'minecraft_account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function liftedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lifted_by');
    }

    public function isActive(): bool
    {
        if ($this->lifted_at !== null) {
            return false;
        }

        if ($this->is_permanent) {
            return true;
        }

        return $this->ends_at === null || $this->ends_at->isFuture();
    }
}
