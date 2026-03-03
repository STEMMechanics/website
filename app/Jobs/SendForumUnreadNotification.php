<?php

namespace App\Jobs;

use App\Mail\ForumUnreadNotification;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class SendForumUnreadNotification implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $uniqueFor = 1800;

    public function __construct(
        public string $userId,
    ) {
        $this->onQueue('mail');
    }

    public function uniqueId(): string
    {
        return 'forum-unread-user-'.$this->userId;
    }

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        if (! $user || trim((string) $user->email) === '') {
            return;
        }

        $topics = ForumTopic::unreadForUserQuery($user)
            ->with('category')
            ->get();

        if ($topics->isEmpty()) {
            return;
        }

        $topicDigests = collect();
        $emailedTopicIds = [];

        foreach ($topics as $topic) {
            $state = ForumTopicUserState::query()->firstOrCreate(
                [
                    'forum_topic_id' => (string) $topic->id,
                    'user_id' => (string) $user->id,
                ]
            );

            $cutoff = $state->last_read_at;
            if ($state->last_emailed_at && ($cutoff === null || $state->last_emailed_at->gt($cutoff))) {
                $cutoff = $state->last_emailed_at;
            }

            $posts = ForumPost::query()
                ->with('user')
                ->where('forum_topic_id', $topic->id)
                ->where(function ($query) use ($cutoff) {
                    if ($cutoff === null) {
                        return $query;
                    }

                    return $query->where('created_at', '>', $cutoff);
                })
                ->where(function ($query) use ($user) {
                    $query
                        ->whereNull('user_id')
                        ->orWhere('user_id', '!=', (string) $user->id);
                })
                ->orderBy('created_at')
                ->orderBy('id')
                ->get();

            if ($posts->isEmpty()) {
                continue;
            }

            $topicDigests->push([
                'topic' => $topic,
                'posts' => $posts,
                'url' => route('forum.topic.show', [
                    'categorySlug' => $topic->category->slug,
                    'topicSlug' => $topic->slug,
                ]),
            ]);
            $emailedTopicIds[] = (string) $topic->id;
        }

        if ($topicDigests->isEmpty()) {
            return;
        }

        $topicDigests = $topicDigests
            ->sortByDesc(fn (array $digest) => $digest['posts']->last()?->created_at?->timestamp ?? 0)
            ->values();

        dispatch(new SendEmail($user->email, new ForumUnreadNotification($user, $topicDigests)))->onQueue('mail');

        ForumTopicUserState::query()
            ->where('user_id', (string) $user->id)
            ->whereIn('forum_topic_id', $emailedTopicIds)
            ->update([
                'last_emailed_at' => now(),
            ]);
    }
}
