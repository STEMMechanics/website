<?php

namespace App\Models;

use App\Jobs\DeliverMinecraftWebhook;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class MinecraftWebhookLog extends Model
{
    use HasFactory;

    public const DIRECTION_INBOUND = 'inbound';
    public const DIRECTION_OUTBOUND = 'outbound';

    public const STATUS_QUEUED = 'queued';
    public const STATUS_PENDING = 'pending';
    public const STATUS_DELIVERED = 'delivered';
    public const STATUS_FAILED = 'failed';
    public const STATUS_RECEIVED = 'received';
    public const STATUS_IGNORED = 'ignored';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_DUPLICATE = 'duplicate';

    protected $fillable = [
        'direction',
        'status',
        'event',
        'delivery_id',
        'method',
        'target_url',
        'request_headers',
        'payload',
        'raw_body',
        'response_status',
        'response_body',
        'error_message',
        'attempt_count',
        'last_attempted_at',
        'processed_at',
        'delivered_at',
        'failed_at',
        'retried_from_id',
    ];

    protected $casts = [
        'request_headers' => 'array',
        'payload' => 'array',
        'last_attempted_at' => 'datetime',
        'processed_at' => 'datetime',
        'delivered_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function retriedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'retried_from_id');
    }

    public function retries(): HasMany
    {
        return $this->hasMany(self::class, 'retried_from_id');
    }

    public function nextRetryAt(): ?Carbon
    {
        if ($this->direction !== self::DIRECTION_OUTBOUND || $this->status !== self::STATUS_PENDING || ! $this->last_attempted_at) {
            return null;
        }

        $backoff = DeliverMinecraftWebhook::BACKOFF_SECONDS;
        $index = min(max($this->attempt_count - 1, 0), count($backoff) - 1);
        $delaySeconds = $backoff[$index];

        return Carbon::instance($this->last_attempted_at)->addSeconds($delaySeconds);
    }

    public function errorSummary(): ?string
    {
        $message = trim((string) ($this->error_message ?? ''));
        if ($message === '') {
            return null;
        }

        if (preg_match('/^cURL error \d+:\s*(.+?)(?:\s*\(see https?:\/\/[^)]+\))?(?:\s+for\s+\S+)?$/i', $message, $matches)) {
            return trim((string) $matches[1]);
        }

        $message = preg_replace('/\s*\(see https?:\/\/[^)]+\)/i', '', $message) ?? $message;
        $message = preg_replace('/\s+for\s+https?:\/\/\S+$/i', '', $message) ?? $message;

        return trim($message);
    }
}
