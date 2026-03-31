<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * @property int|null $reply_depth
 * @property-read ForumTopic $topic
 * @property-read User|null $user
 * @property-read ForumPost|null $parentPost
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPostAttachment> $attachments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, ForumPostReaction> $reactions
 */
class ForumPost extends Model
{
    use HasFactory, UUID;

    protected $casts = [
        'is_topic_starter' => 'boolean',
        'is_approved' => 'boolean',
        'edited_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $fillable = [
        'forum_topic_id',
        'parent_forum_post_id',
        'user_id',
        'is_topic_starter',
        'approved_by_user_id',
        'is_approved',
        'body',
        'edited_at',
        'deleted_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $post): void {
            if (array_key_exists('is_topic_starter', $post->getAttributes())) {
                return;
            }

            $topicId = trim((string) $post->forum_topic_id);
            if ($topicId === '') {
                return;
            }

            $post->is_topic_starter = ! static::query()
                ->where('forum_topic_id', $topicId)
                ->exists();
        });
    }

    /**
     * @return BelongsTo<ForumTopic, $this>
     */
    public function topic(): BelongsTo
    {
        return $this->belongsTo(ForumTopic::class, 'forum_topic_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<ForumPost, $this>
     */
    public function parentPost(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_forum_post_id');
    }

    /**
     * @return HasMany<ForumPost, $this>
     */
    public function childPosts(): HasMany
    {
        return $this->hasMany(self::class, 'parent_forum_post_id');
    }

    /**
     * @return HasMany<ForumPostAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(ForumPostAttachment::class, 'forum_post_id')
            ->orderBy('sort_order')
            ->orderBy('created_at');
    }

    /**
     * @return HasMany<ForumPostReaction, $this>
     */
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

    /**
     * @return Collection<int, string>
     */
    public function reactionUsersFor(string $type): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int, ForumPostReaction> $reactions */
        $reactions = $this->reactions;

        /** @var Collection<int, string> $users */
        $users = $reactions
            ->where('type', $type)
            ->map(fn (ForumPostReaction $reaction): string => (string) ($reaction->user?->forumDisplayName() ?: 'deleted'))
            ->values();

        return $users;
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

        if ($this->isDeleted() || ! $this->is_approved) {
            return false;
        }

        return (string) $this->user_id === (string) $user->id;
    }

    public function isDeleted(): bool
    {
        return $this->deleted_at !== null;
    }

    public function softDeleteToPlaceholder(): void
    {
        if ($this->isDeleted()) {
            return;
        }

        $this->body = '<p><em>Post was deleted</em></p>';
        $this->deleted_at = now();
        $this->edited_at = now();
        $this->save();
    }
}
