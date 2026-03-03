<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class ForumPost extends Model
{
    use HasFactory, UUID;

    protected $casts = [
        'edited_at' => 'datetime',
    ];

    protected $fillable = [
        'forum_topic_id',
        'parent_forum_post_id',
        'user_id',
        'body',
        'edited_at',
    ];

    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_forum_post_id');
    }

    public function childPosts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_forum_post_id');
    }

    public function reactions(): HasMany
    {
        return $this->hasMany(ForumPostReaction::class, 'forum_post_id');
    }

    public function reactionCountFor(string $type): int
    {
        return $this->reactions
            ->where('type', $type)
            ->count();
    }

    public function reactionUsersFor(string $type): Collection
    {
        return $this->reactions
            ->where('type', $type)
            ->map(fn (ForumPostReaction $reaction) => $reaction->user?->username ?: $reaction->user?->getName() ?: 'Deleted user')
            ->values();
    }

    public function reactionTooltipFor(string $type): string
    {
        $users = $this->reactionUsersFor($type);

        if ($users->isEmpty()) {
            return 'No reactions yet';
        }

        return $users->implode(', ');
    }

    public function reactionTypeFor(?User $user): ?string
    {
        if (! $user) {
            return null;
        }

        $reaction = $this->reactions->firstWhere('user_id', (string) $user->id);

        return $reaction instanceof ForumPostReaction ? $reaction->type : null;
    }

    public function canEdit(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        return (string) $this->user_id === (string) $user->id;
    }
}
