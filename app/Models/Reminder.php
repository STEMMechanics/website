<?php

namespace App\Models;

use App\Services\ReminderService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Reminder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'kind',
        'remindable_type',
        'remindable_id',
        'source_type',
        'source_id',
        'recipient_user_id',
        'recipient_email',
        'subject',
        'message',
        'action_url',
        'status',
        'scheduled_at',
        'queued_at',
        'sent_at',
        'failed_at',
        'failure_message',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'queued_at' => 'datetime',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public function remindable(): MorphTo
    {
        return $this->morphTo();
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }

    public function isCompletedWorkshopTask(): bool
    {
        if ($this->kind !== ReminderService::WORKSHOP_TASK_KIND
            || ! $this->remindable instanceof Workshop
            || ! is_numeric($this->source_id)) {
            return false;
        }

        return collect($this->remindable->run_sheet_completed_task_ids ?? [])
            ->map(fn ($id) => (int) $id)
            ->contains((int) $this->source_id);
    }
}
