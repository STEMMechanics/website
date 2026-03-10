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

    public function troubleshootingHint(): ?string
    {
        $summary = strtolower((string) $this->errorSummary());
        if ($summary === '') {
            return null;
        }

        $endpoint = $this->targetHostPort();

        if (
            str_contains($summary, 'connection timed out')
            || str_contains($summary, "couldn't connect to server")
            || str_contains($summary, 'failed to connect')
            || str_contains($summary, 'connection refused')
        ) {
            $hint = 'Check TCP reachability';
            if ($endpoint !== null) {
                $hint .= ' to '.$endpoint;
            }
            $hint .= ' from the app server.';

            if ($this->usesPublicCustomPort()) {
                $hint .= ' If Laravel and the plugin share a host or private network, use an internal HTTP URL such as http://127.0.0.1:8125/stemcraft/webhook instead of a public hostname.';
            }

            return $hint;
        }

        if (str_contains($summary, 'could not resolve host')) {
            $hint = 'Check DNS resolution';
            if ($endpoint !== null) {
                $hint .= ' for '.$endpoint;
            }
            $hint .= ' from the app server and verify the configured webhook URL.';

            return $hint;
        }

        return null;
    }

    private function targetHostPort(): ?string
    {
        $targetUrl = trim((string) ($this->target_url ?? ''));
        if ($targetUrl === '') {
            return null;
        }

        $host = parse_url($targetUrl, PHP_URL_HOST);
        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        $port = parse_url($targetUrl, PHP_URL_PORT);

        return is_int($port) ? $host.':'.$port : $host;
    }

    private function usesPublicCustomPort(): bool
    {
        $targetUrl = trim((string) ($this->target_url ?? ''));
        if ($targetUrl === '') {
            return false;
        }

        $host = parse_url($targetUrl, PHP_URL_HOST);
        $port = parse_url($targetUrl, PHP_URL_PORT);

        if (! is_string($host) || trim($host) === '' || ! is_int($port)) {
            return false;
        }

        if (in_array($host, ['localhost', '127.0.0.1', '::1'], true)) {
            return false;
        }

        if (! str_contains($host, '.')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            $isPublicIp = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;

            if (! $isPublicIp) {
                return false;
            }
        }

        return ! in_array($port, [80, 443], true);
    }
}
