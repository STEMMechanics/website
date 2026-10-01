<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class ServerBackupRun extends Model
{
    use HasUuids;

    public const TYPE_DATABASE = 'database';

    public const TYPE_FILES = 'files';

    public const TYPE_INSPECTION = 'inspection';

    public const TYPE_BACKUP_ARCHIVE = 'backup_archive';

    public const TYPE_MEDIA_ARCHIVE = 'media_archive';

    public const TYPE_FINANCE_ARCHIVE = 'finance_archive';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type', 'status', 'progress', 'message', 'error_message', 'result',
        'requested_by', 'started_at', 'finished_at',
    ];

    protected $casts = [
        'progress' => 'integer',
        'result' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_FAILED], true);
    }

    public function staleAfterMinutes(): int
    {
        return match ($this->type) {
            self::TYPE_DATABASE => 15,
            self::TYPE_INSPECTION => 30,
            default => 120,
        };
    }

    public function recoverIfStale(): bool
    {
        if ($this->isFinished()) {
            return false;
        }

        $lastActivity = $this->updated_at ?? $this->started_at ?? $this->created_at;
        if ($lastActivity === null || ! $lastActivity->lt(now()->subMinutes($this->staleAfterMinutes()))) {
            return false;
        }

        $this->forceFill([
            'status' => self::STATUS_FAILED,
            'progress' => 100,
            'message' => 'Backup did not complete.',
            'error_message' => 'The queue worker stopped or did not finish this run. Start a queue worker and try again.',
            'finished_at' => now(),
        ])->saveQuietly();

        return true;
    }

    public function payload(): array
    {
        $downloadUrl = $this->status === self::STATUS_COMPLETED && filled($this->result['archive_path'] ?? null)
            ? route('admin.server.backups.download', $this)
            : null;

        return [
            'id' => (string) $this->id,
            'type' => (string) $this->type,
            'status' => (string) $this->status,
            'progress' => (int) $this->progress,
            'message' => (string) ($this->message ?? ''),
            'error_message' => (string) ($this->error_message ?? ''),
            'finished' => $this->isFinished(),
            'download_url' => $downloadUrl,
        ];
    }
}
