<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassChatMessage extends Model
{
    use HasFactory;
    use UUID;

    protected $fillable = [
        'class_session_id',
        'user_id',
        'raw_message',
        'display_message',
        'is_blocked',
        'moderation_reason',
        'moderation_reason_label',
        'moderation_reason_detail',
    ];

    protected $casts = [
        'is_blocked' => 'boolean',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
