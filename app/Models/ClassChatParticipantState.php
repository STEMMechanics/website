<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassChatParticipantState extends Model
{
    use HasFactory;
    use UUID;

    protected $fillable = [
        'class_session_id',
        'user_id',
        'disabled_by_user_id',
        'disabled_at',
    ];

    protected $casts = [
        'disabled_at' => 'datetime',
    ];

    public function classSession(): BelongsTo
    {
        return $this->belongsTo(ClassSession::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function disabledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disabled_by_user_id');
    }
}
