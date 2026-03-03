<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftEventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'minecraft_account_id',
        'event',
        'occurred_at',
        'platform',
        'uuid',
        'username',
        'server_name',
        'message',
        'payload',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'payload' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class, 'minecraft_account_id');
    }
}
