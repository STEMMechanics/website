<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ForumTopic extends Model
{
    use HasFactory, UUID;

    protected $fillable = [
        'forum_category_id',
        'user_id',
        'last_post_user_id',
        'title',
        'slug',
        'is_locked',
        'is_pinned',
        'view_count',
        'last_post_at',
    ];

    protected $casts = [
        'is_locked' => 'boolean',
        'is_pinned' => 'boolean',
        'view_count' => 'integer',
        'last_post_at' => 'datetime',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ForumCategory::class, 'forum_category_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lastPostUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_post_user_id');
    }

    public function posts(): HasMany
    {
        return $this->hasMany(ForumPost::class)->orderBy('created_at')->orderBy('id');
    }

    public function userStates(): HasMany
    {
        return $this->hasMany(ForumTopicUserState::class, 'forum_topic_id');
    }

    public function firstPost(): HasOne
    {
        return $this->hasOne(ForumPost::class)->oldestOfMany('created_at');
    }

    public function canRead(?User $user): bool
    {
        return $this->category->canRead($user);
    }

    public function canReply(?User $user): bool
    {
        if (! $this->category->canWrite($user)) {
            return false;
        }

        return ! $this->is_locked;
    }

    public static function generateUniqueSlug(string $title, string $categoryId, ?string $ignoreTopicId = null): string
    {
        $base = trim((string) Str::slug($title));
        if ($base === '') {
            $base = 'topic';
        }

        $candidate = $base;
        $suffix = 1;

        while (static::query()
            ->where('forum_category_id', $categoryId)
            ->when($ignoreTopicId !== null && $ignoreTopicId !== '', fn ($query) => $query->where('id', '!=', $ignoreTopicId))
            ->where('slug', $candidate)
            ->exists()) {
            $append = (string) $suffix++;
            $candidate = substr($base, 0, max(1, 180 - strlen($append) - 1)).'-'.$append;
        }

        return $candidate;
    }

    public static function unreadForUserQuery(User $user): Builder
    {
        return static::query()
            ->select('forum_topics.*')
            ->join('forum_topic_user_states as forum_topic_state', function ($join) use ($user) {
                $join->on('forum_topic_state.forum_topic_id', '=', 'forum_topics.id')
                    ->where('forum_topic_state.user_id', '=', (string) $user->id);
            })
            ->where('forum_topic_state.notifications_enabled', true)
            ->whereNotNull('forum_topics.last_post_at')
            ->where(function ($query) use ($user) {
                $query
                    ->whereNull('forum_topic_state.last_read_at')
                    ->orWhereColumn('forum_topic_state.last_read_at', '<', 'forum_topics.last_post_at');
            })
            ->where(function ($query) use ($user) {
                $query
                    ->whereNull('forum_topics.last_post_user_id')
                    ->orWhere('forum_topics.last_post_user_id', '!=', (string) $user->id);
            })
            ->distinct();
    }

    public static function unreadCountForUser(User $user): int
    {
        return static::unreadForUserQuery($user)
            ->pluck('forum_topics.id')
            ->count();
    }

    public static function unreadTopicIdsForUser(User $user, Collection $topics): array
    {
        $topicIds = $topics
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (string) $id)
            ->values();

        if ($topicIds->isEmpty()) {
            return [];
        }

        return static::unreadForUserQuery($user)
            ->whereIn('forum_topics.id', $topicIds->all())
            ->pluck('forum_topics.id')
            ->map(fn ($id) => (string) $id)
            ->all();
    }
}
