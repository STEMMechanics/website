<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'minecraft_account_id',
        'session_uuid',
        'server_name',
        'logged_in_at',
        'logged_out_at',
        'duration_seconds',
    ];

    protected $casts = [
        'logged_in_at' => 'datetime',
        'logged_out_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class, 'minecraft_account_id');
    }

    public function resolvedDurationSeconds(): ?int
    {
        if ($this->logged_out_at !== null) {
            return max(0, $this->logged_in_at->diffInSeconds($this->logged_out_at));
        }

        if ($this->duration_seconds === null) {
            return null;
        }

        return max(0, (int) $this->duration_seconds);
    }

    public function formattedDuration(): ?string
    {
        $seconds = $this->resolvedDurationSeconds();
        if ($seconds === null) {
            return null;
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remainingSeconds = $seconds % 60;

        return sprintf('%02d:%02d:%02d', $hours, $minutes, $remainingSeconds);
    }
}
