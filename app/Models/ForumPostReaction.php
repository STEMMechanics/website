<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ForumPostReaction extends Model
{
    use HasFactory, UUID;

    public const TYPE_LOVE = 'love';
    public const TYPE_LIKE = 'like';
    public const TYPE_DISLIKE = 'dislike';

    public const TYPES = [
        self::TYPE_LOVE,
        self::TYPE_LIKE,
        self::TYPE_DISLIKE,
    ];

    protected $fillable = [
        'forum_post_id',
        'user_id',
        'type',
    ];

    public function post(): BelongsTo
    {
        return $this->belongsTo(ForumPost::class, 'forum_post_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
