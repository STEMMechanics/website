<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumTopicUserState extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'forum_topic_id',
        'user_id',
        'last_read_at',
        'last_emailed_at',
        'notifications_enabled',
    ];

    protected $casts = [
        'last_read_at' => 'datetime',
        'last_emailed_at' => 'datetime',
        'notifications_enabled' => 'boolean',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
