<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class ClassHelpRequest extends Model
{
    use HasFactory;
    use UUID;

    public const TYPE_HAND = 'hand';
    public const TYPE_SCREEN = 'screen';
    public const TYPE_CAMERA = 'camera';

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_DONE = 'done';
    public const STATUS_REJECTED = 'rejected';

    public const TYPES = [
        self::TYPE_HAND,
        self::TYPE_SCREEN,
        self::TYPE_CAMERA,
    ];

    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_DONE,
        self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'class_session_id',
        'user_id',
        'target_participant_identity',
        'target_username',
        'target_display_name',
        'requested_by_user_id',
        'type',
        'status',
        'resolution_reason',
        'approved_by_user_id',
        'approved_at',
        'resolved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function typeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_HAND => 'Raised hand',
            self::TYPE_CAMERA => 'Camera',
            default => 'Screen share',
        };
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED => 'Active',
            self::STATUS_DONE => 'Done',
            self::STATUS_REJECTED => 'Rejected',
            default => 'Pending',
        };
    }

    public function resolvedAtHuman(): ?string
    {
        return $this->resolved_at instanceof Carbon ? $this->resolved_at->toDateTimeString() : null;
    }
}
