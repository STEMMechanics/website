<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SentSms extends Model
{
    use HasFactory;

    public const STATUS_QUEUED = 'queued';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'recipient',
        'recipient_name',
        'message',
        'status',
        'from_number',
        'origin',
        'reference',
        'provider_message_id',
        'response_status',
        'response_payload',
        'context',
        'initiated_by_user_id',
        'initiated_by_name',
        'error_message',
        'sent_at',
        'failed_at',
    ];

    protected $casts = [
        'response_payload' => 'array',
        'context' => 'array',
        'sent_at' => 'datetime',
        'failed_at' => 'datetime',
        'response_status' => 'integer',
    ];

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }

    /**
     * @return HasMany<InboundSms, $this>
     */
    public function inboundSms(): HasMany
    {
        return $this->hasMany(InboundSms::class);
    }
}
