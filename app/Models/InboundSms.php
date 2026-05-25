<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InboundSms extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider',
        'topic',
        'incoming_id',
        'original_message_id',
        'sent_sms_id',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'originator',
        'destination',
        'message',
        'received_at',
        'opted_out',
        'payload',
    ];

    protected $casts = [
        'acknowledged_at' => 'datetime',
        'received_at' => 'datetime',
        'opted_out' => 'boolean',
        'payload' => 'array',
    ];

    public function sentSms(): BelongsTo
    {
        return $this->belongsTo(SentSms::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }
}
