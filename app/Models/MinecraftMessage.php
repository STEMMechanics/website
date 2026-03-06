<?php

namespace App\Models;

use App\Services\MinecraftMessageModerationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MinecraftMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'minecraft_account_id',
        'occurred_at',
        'message_type',
        'platform',
        'uuid',
        'username',
        'server_name',
        'world',
        'x',
        'y',
        'z',
        'yaw',
        'pitch',
        'raw_message',
        'filtered_message',
        'passed',
        'failure_reason',
        'failure_detail',
        'context',
        'payload',
        'admin_failure_notification_queued_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'x' => 'float',
        'y' => 'float',
        'z' => 'float',
        'yaw' => 'float',
        'pitch' => 'float',
        'passed' => 'boolean',
        'context' => 'array',
        'payload' => 'array',
        'admin_failure_notification_queued_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(MinecraftAccount::class, 'minecraft_account_id');
    }

    public function displayMessage(): string
    {
        if ($this->passed) {
            return (string) $this->raw_message;
        }

        $filtered = trim((string) ($this->filtered_message ?? ''));
        if ($filtered !== '') {
            return $filtered;
        }

        return trim((string) SiteOption::value(
            'moderation.content-filter.blocked-message-placeholder',
            MinecraftMessageModerationService::DEFAULT_BLOCKED_PLACEHOLDER,
        )) ?: MinecraftMessageModerationService::DEFAULT_BLOCKED_PLACEHOLDER;
    }

    public function failureLabel(): ?string
    {
        return match ($this->failure_reason) {
            'profanity' => 'Blasp profanity filter',
            'custom_regex' => 'Custom regex pattern',
            'all_caps' => 'All-caps rule',
            'repeated_characters' => 'Repeated characters rule',
            'repeated_words' => 'Repeated words rule',
            default => null,
        };
    }

    public function failureSummary(): string
    {
        if ($this->passed) {
            return 'Allowed';
        }

        $filtered = trim((string) ($this->filtered_message ?? ''));
        if ($filtered !== '') {
            return $filtered;
        }

        $label = $this->failureLabel() ?? 'Blocked';
        if ($this->failure_reason === 'custom_regex' && trim((string) ($this->failure_detail ?? '')) !== '') {
            return $label.': '.trim((string) $this->failure_detail);
        }

        return $label;
    }

    public function formattedLocation(): string
    {
        $coordinates = collect([$this->x, $this->y, $this->z])
            ->map(fn ($value) => number_format((float) $value, 3, '.', ''))
            ->implode(', ');

        return trim((string) $this->world).' @ '.$coordinates;
    }
}
