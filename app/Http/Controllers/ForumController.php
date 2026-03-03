<?php

namespace App\Http\Controllers;

use App\Contracts\ContentFilter;
use App\Jobs\SendEmail;
use App\Mail\ForumPostReport;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostReaction;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Support\ForumContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForumController extends Controller
{
    public function __construct(
        private readonly ContentFilter $contentFilter,
    ) {
    }

    public function index(Request $request): View
    {
        ['categories' => $categories, 'unreadCategoryIds' => $unreadCategoryIds] = $this->buildForumIndexData($request->user());

        return view('forum.index', [
            'categories' => $categories,
            'unreadCategoryIds' => $unreadCategoryIds,
        ]);
    }

    public function showCategory(Request $request, string $categorySlug): View
    {
        $category = $this->findCategoryOrFail($categorySlug);
        abort_if($category->isDivider(), 404);
        $user = $request->user();
        abort_unless($category->canRead($user), 404);

        [
            'topics' => $topics,
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestActivityAt,
            'latestActivityAuthorName' => $latestActivityAuthorName,
        ] = $this->buildForumCategoryData($category, $user);

        return view('forum.category', [
            'category' => $category,
            'topics' => $topics,
            'canWrite' => $category->canWrite($user),
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestActivityAt,
            'latestActivityAuthorName' => $latestActivityAuthorName,
        ]);
    }

    public function createTopic(Request $request, string $categorySlug): View
    {
        $category = $this->findCategoryOrFail($categorySlug);
        abort_if($category->isDivider(), 404);
        abort_unless($category->canWrite($request->user()), 403);

        return view('forum.topic-create', [
            'category' => $category,
        ]);
    }

    public function storeTopic(Request $request, string $categorySlug): RedirectResponse
    {
        $category = $this->findCategoryOrFail($categorySlug);
        abort_if($category->isDivider(), 404);
        abort_unless($category->canWrite($request->user()), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ]);

        $body = ForumContent::normalize((string) $validated['body']);
        if (! ForumContent::hasMeaningfulContent($body)) {
            return back()
                ->withErrors(['body' => 'Post content cannot be empty.'])
                ->withInput();
        }

        $titleFilter = $this->contentFilter->inspect((string) $validated['title'], 'forum');
        if ($titleFilter->blocked) {
            return back()
                ->withErrors(['title' => $titleFilter->message ?? 'Thread title is not allowed.'])
                ->withInput();
        }

        $bodyFilter = $this->contentFilter->inspect($body, 'forum');
        if ($bodyFilter->blocked) {
            return back()
                ->withErrors(['body' => $bodyFilter->message ?? 'Post content is not allowed.'])
                ->withInput();
        }

        $topic = new ForumTopic();
        $topic->forum_category_id = (string) $category->id;
        $topic->user_id = (string) $request->user()->id;
        $topic->title = trim((string) $validated['title']);
        $topic->slug = ForumTopic::generateUniqueSlug($topic->title, (string) $category->id);
        $topic->last_post_at = now();
        $topic->last_post_user_id = (string) $request->user()->id;
        $topic->save();

        $post = new ForumPost();
        $post->forum_topic_id = (string) $topic->id;
        $post->user_id = (string) $request->user()->id;
        $post->body = $body;
        $post->save();

        $this->ensureTopicNotificationsEnabledForPoster($topic, $request->user());
        $this->markTopicRead($topic, $request->user(), $post->created_at);

        session()->flash('message', 'Your thread has been saved.');
        session()->flash('message-title', 'Thread created');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]).'#post-'.$post->id);
    }

    public function showTopic(Request $request, string $categorySlug, string $topicSlug): View
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();
        abort_unless($topic->canRead($user), 404);

        $this->recordTopicView($request, $topic);

        $topicView = $this->buildTopicViewData($topic, $request);

        if ($user) {
            $this->markTopicRead($topic, $user, $topic->last_post_at ?? now());
        }

        return view('forum.topic', [
            'category' => $topic->category,
            'topic' => $topic,
            ...$topicView,
        ]);
    }

    public function storePost(Request $request, string $categorySlug, string $topicSlug): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($topic->canReply($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'reply_to_post_id' => ['nullable', 'string'],
        ]);

        $body = ForumContent::normalize((string) $validated['body']);
        if (! ForumContent::hasMeaningfulContent($body)) {
            return back()
                ->withErrors(['body' => 'Reply content cannot be empty.'])
                ->withInput();
        }

        $bodyFilter = $this->contentFilter->inspect($body, 'forum');
        if ($bodyFilter->blocked) {
            return back()
                ->withErrors(['body' => $bodyFilter->message ?? 'Reply content is not allowed.'])
                ->withInput();
        }

        $post = new ForumPost();
        $post->forum_topic_id = (string) $topic->id;
        $post->parent_forum_post_id = $this->validatedReplyParentId($topic, $validated['reply_to_post_id'] ?? null);
        $post->user_id = (string) $request->user()->id;
        $post->body = $body;
        $post->save();

        $topic->last_post_at = $post->created_at;
        $topic->last_post_user_id = (string) $request->user()->id;
        $topic->save();
        $this->ensureTopicNotificationsEnabledForPoster($topic, $request->user());
        $this->markTopicRead($topic, $request->user(), $post->created_at);

        $replySort = $this->normalizedReplySort($request->query('sort'));
        $redirectParams = [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
        ];

        if ($replySort === 'oldest') {
            $replyCount = max(0, $topic->posts()->count() - 1);
            $redirectParams['page'] = max(1, (int) ceil($replyCount / 20));
            $redirectParams['sort'] = 'oldest';
        }

        session()->flash('message', 'Reply posted.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', $redirectParams).'#post-'.$post->id);
    }

    public function updatePost(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);
        abort_unless($forumPost->canEdit($request->user()), 403);

        $validated = $request->validate([
            'body' => ['required', 'string'],
            'modal_mode' => ['nullable', 'string'],
            'edit_post_id' => ['nullable', 'string'],
        ]);

        $body = ForumContent::normalize((string) $validated['body']);
        if (! ForumContent::hasMeaningfulContent($body)) {
            return back()
                ->withErrors(['body' => 'Post content cannot be empty.'])
                ->withInput();
        }

        $bodyFilter = $this->contentFilter->inspect($body, 'forum');
        if ($bodyFilter->blocked) {
            return back()
                ->withErrors(['body' => $bodyFilter->message ?? 'Post content is not allowed.'])
                ->withInput();
        }

        if ($forumPost->body !== $body) {
            $forumPost->body = $body;
            $forumPost->edited_at = now();
            $forumPost->save();
        }

        session()->flash('message', 'Post updated.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
            'sort' => $this->normalizedReplySort($request->query('sort')),
        ]).'#post-'.$forumPost->id);
    }

    public function toggleReaction(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost): RedirectResponse|JsonResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($topic->canRead($request->user()), 404);
        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);

        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', ForumPostReaction::TYPES)],
        ]);

        $type = (string) $validated['type'];
        $user = $request->user();
        $existingReaction = ForumPostReaction::query()
            ->where('forum_post_id', $forumPost->id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingReaction && $existingReaction->type === $type) {
            $existingReaction->delete();
            $message = ucfirst($type).' removed.';
        } else {
            ForumPostReaction::query()->updateOrCreate(
                [
                    'forum_post_id' => (string) $forumPost->id,
                    'user_id' => (string) $user->id,
                ],
                [
                    'type' => $type,
                ]
            );
            $message = ucfirst($type).' saved.';
        }

        $forumPost->load('reactions.user');

        if ($request->expectsJson()) {
            return response()->json([
                'message' => $message,
                'reactions' => $this->reactionPayload($forumPost, $user),
            ]);
        }

        session()->flash('message', $message);
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
            'sort' => $this->normalizedReplySort($request->query('sort')),
            'reply_to' => $request->query('reply_to'),
        ]).'#post-'.$forumPost->id);
    }

    public function reportPost(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($topic->canRead($request->user()), 404);
        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
            'report_post_id' => ['nullable', 'string'],
            'report_author' => ['nullable', 'string'],
            'report_action' => ['nullable', 'string'],
        ]);

        $postUrl = route('forum.topic.show', [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
            'sort' => $this->normalizedReplySort($request->query('sort')),
        ]).'#post-'.$forumPost->id;

        dispatch(new SendEmail(
            $this->moderationRecipientAddress(),
            new ForumPostReport(
                post: $forumPost->loadMissing('user', 'topic.category'),
                reporter: $request->user(),
                reason: trim((string) $validated['reason']),
                postUrl: $postUrl,
            )
        ))->onQueue('mail');

        session()->flash('message', 'The post has been reported to the moderation team.');
        session()->flash('message-title', 'Report submitted');
        session()->flash('message-type', 'success');

        return redirect()->to($postUrl);
    }

    public function unreadSummary(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'count' => ForumTopic::unreadCountForUser($user),
        ]);
    }

    public function indexSnapshot(Request $request): JsonResponse
    {
        ['categories' => $categories, 'unreadCategoryIds' => $unreadCategoryIds] = $this->buildForumIndexData($request->user());

        return response()->json([
            'categoriesHtml' => $categories->isEmpty()
                ? ''
                : view('forum.partials.category-list', [
                    'categories' => $categories,
                    'unreadCategoryLookup' => array_flip($unreadCategoryIds),
                ])->render(),
        ]);
    }

    public function categorySnapshot(Request $request, string $categorySlug): JsonResponse
    {
        $category = $this->findCategoryOrFail($categorySlug);
        abort_if($category->isDivider(), 404);
        $user = $request->user();
        abort_unless($category->canRead($user), 404);

        [
            'topics' => $topics,
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestActivityAt,
            'latestActivityAuthorName' => $latestActivityAuthorName,
        ] = $this->buildForumCategoryData($category, $user);

        return response()->json([
            'metaHtml' => view('forum.partials.category-meta', [
                'threadCount' => $threadCount,
                'commentCount' => $commentCount,
                'viewCount' => $viewCount,
                'latestActivityAt' => $latestActivityAt,
                'latestActivityAuthorName' => $latestActivityAuthorName,
            ])->render(),
            'threadsHtml' => $topics->isEmpty()
                ? ''
                : view('forum.partials.thread-list', [
                    'topics' => $topics,
                    'category' => $category,
                    'unreadTopicLookup' => array_flip($unreadTopicIds),
                ])->render(),
            'paginationHtml' => $topics->appends($request->query())->links()->toHtml(),
            'emptyHtml' => $topics->isEmpty()
                ? '<div class="rounded-lg border border-gray-200 bg-white p-6 text-gray-600">No threads have been created in this category yet.</div>'
                : '',
        ]);
    }

    public function topicSnapshot(Request $request, string $categorySlug, string $topicSlug): JsonResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();
        abort_unless($topic->canRead($user), 404);

        $this->markTopicRead($topic, $user, $topic->last_post_at ?? now());

        $topicView = $this->buildTopicViewData($topic, $request);

        return response()->json([
            'topicMetaHtml' => view('forum.partials.topic-meta', [
                'category' => $topic->category,
                'topic' => $topic,
                'canReply' => $topicView['canReply'],
                'commentCount' => $topicView['commentCount'],
                'lastCommentPost' => $topicView['lastCommentPost'],
                'lastCommentAuthorName' => $topicView['lastCommentAuthorName'],
                'notificationsEnabled' => $topicView['notificationsEnabled'],
            ])->render(),
            'firstPostHtml' => $topicView['firstPost']
                ? view('forum.partials.post', [
                    'post' => $topicView['firstPost'],
                    'isFirstPost' => true,
                    'replyLabel' => 'Thread Post',
                    'category' => $topic->category,
                    'topic' => $topic,
                    'replySort' => $topicView['replySort'],
                    'canReply' => $topicView['canReply'],
                    'replyDraftBody' => $this->replyDraftBodyForPost($topicView['firstPost']),
                ])->render()
                : '',
            'repliesHtml' => view('forum.partials.replies', [
                'posts' => $topicView['posts'],
                'replySort' => $topicView['replySort'],
                'category' => $topic->category,
                'topic' => $topic,
                'canReply' => $topicView['canReply'],
            ])->render(),
            'paginationHtml' => $topicView['posts']->appends($request->query())->links()->toHtml(),
            'replyPanelHtml' => view('forum.partials.reply-panel', [
                'topic' => $topic,
                'canReply' => $topicView['canReply'],
            ])->render(),
            'reactionState' => $this->reactionStatePayload($topicView['firstPost'], $topicView['posts'], $user),
        ]);
    }

    public function toggleNotifications(Request $request, string $categorySlug, string $topicSlug): RedirectResponse|JsonResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();
        abort_unless($topic->canRead($user), 404);

        $enabled = $request->boolean('notifications_enabled');

        ForumTopicUserState::query()->updateOrCreate(
            [
                'forum_topic_id' => (string) $topic->id,
                'user_id' => (string) $user->id,
            ],
            [
                'notifications_enabled' => $enabled,
            ]
        );

        session()->flash('message', $enabled ? 'Notifications enabled for this thread.' : 'Notifications disabled for this thread.');
        session()->flash('message-title', 'Thread updated');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json([
                'enabled' => $enabled,
                'message' => $enabled ? 'Notifications enabled for this thread.' : 'Notifications disabled for this thread.',
            ]);
        }

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
            'sort' => $this->normalizedReplySort($request->query('sort')),
        ]));
    }

    public function toggleLock(Request $request, string $categorySlug, string $topicSlug): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($request->user()?->isAdmin(), 403);

        $topic->is_locked = ! $topic->is_locked;
        $topic->save();

        session()->flash('message', $topic->is_locked ? 'Thread locked.' : 'Thread unlocked.');
        session()->flash('message-title', 'Thread updated');
        session()->flash('message-type', 'success');

        return back();
    }

    public function togglePin(Request $request, string $categorySlug, string $topicSlug): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($request->user()?->isAdmin(), 403);

        $topic->is_pinned = ! $topic->is_pinned;
        $topic->save();

        session()->flash('message', $topic->is_pinned ? 'Thread pinned.' : 'Thread unpinned.');
        session()->flash('message-title', 'Thread updated');
        session()->flash('message-type', 'success');

        return back();
    }

    public function destroyTopic(Request $request, string $categorySlug, string $topicSlug): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($request->user()?->isAdmin(), 403);

        $redirectUrl = route('forum.category.show', $topic->category->slug);
        $topic->delete();

        session()->flash('message', 'Thread deleted.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->to($redirectUrl);
    }

    public function destroyPost(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);

        $firstPost = $topic->firstPost()->first();
        if ($firstPost && (string) $forumPost->id === (string) $firstPost->id) {
            session()->flash('message', 'Delete the thread to remove its first post.');
            session()->flash('message-title', 'Post not deleted');
            session()->flash('message-type', 'danger');

            return back();
        }

        $forumPost->delete();
        $this->syncTopicActivity($topic);

        session()->flash('message', 'Post deleted.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
            'sort' => $this->normalizedReplySort($request->query('sort')),
        ]));
    }

    private function findCategoryOrFail(string $slug): ForumCategory
    {
        return ForumCategory::query()
            ->where('slug', ForumCategory::normalizeSlug($slug))
            ->firstOrFail();
    }

    private function findTopicOrFail(string $categorySlug, string $topicSlug): ForumTopic
    {
        $category = $this->findCategoryOrFail($categorySlug);
        $normalizedSlug = trim((string) Str::slug($topicSlug));

        return ForumTopic::query()
            ->with(['category', 'user', 'lastPostUser'])
            ->where('forum_category_id', $category->id)
            ->where('slug', $normalizedSlug)
            ->firstOrFail();
    }

    private function normalizedReplySort(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'latest' ? 'latest' : 'oldest';
    }

    private function replyPrefillBody(ForumTopic $topic, Request $request): string
    {
        return '';
    }

    private function replyDraftBodyForPost(?ForumPost $post): string
    {
        return '';
    }

    private function validatedReplyParentId(ForumTopic $topic, mixed $value): ?string
    {
        $postId = trim((string) $value);
        if ($postId === '') {
            return null;
        }

        return ForumPost::query()
            ->where('forum_topic_id', $topic->id)
            ->whereKey($postId)
            ->exists()
            ? $postId
            : null;
    }

    private function recordTopicView(Request $request, ForumTopic $topic): void
    {
        $sessionKey = 'forum.viewed_topics.'.(string) $topic->id;
        $lastViewedAt = $request->session()->get($sessionKey);

        if (is_string($lastViewedAt) && now()->diffInMinutes($lastViewedAt) < 30) {
            return;
        }

        $topic->increment('view_count');
        $request->session()->put($sessionKey, now()->toIso8601String());
    }

    private function syncTopicActivity(ForumTopic $topic): void
    {
        $latestPost = ForumPost::query()
            ->where('forum_topic_id', $topic->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        $topic->last_post_at = $latestPost?->created_at;
        $topic->last_post_user_id = $latestPost?->user_id;
        $topic->save();
    }

    private function markTopicRead(ForumTopic $topic, $user, $readAt): void
    {
        if (! $user) {
            return;
        }

        ForumTopicUserState::query()->updateOrCreate(
            [
                'forum_topic_id' => (string) $topic->id,
                'user_id' => (string) $user->id,
            ],
            [
                'last_read_at' => $readAt ?? now(),
            ]
        );
    }

    private function ensureTopicNotificationsEnabledForPoster(ForumTopic $topic, $user): void
    {
        if (! $user) {
            return;
        }

        $state = ForumTopicUserState::query()->firstOrCreate(
            [
                'forum_topic_id' => (string) $topic->id,
                'user_id' => (string) $user->id,
            ]
        );

        if ($state->notifications_enabled === null) {
            $state->notifications_enabled = true;
            $state->save();
        }
    }

    private function reactionPayload(ForumPost $post, $user): array
    {
        return [
            'current' => $post->reactionTypeFor($user),
            'counts' => [
                ForumPostReaction::TYPE_LOVE => $post->reactionCountFor(ForumPostReaction::TYPE_LOVE),
                ForumPostReaction::TYPE_LIKE => $post->reactionCountFor(ForumPostReaction::TYPE_LIKE),
                ForumPostReaction::TYPE_DISLIKE => $post->reactionCountFor(ForumPostReaction::TYPE_DISLIKE),
            ],
            'tooltips' => [
                ForumPostReaction::TYPE_LOVE => $post->reactionTooltipFor(ForumPostReaction::TYPE_LOVE),
                ForumPostReaction::TYPE_LIKE => $post->reactionTooltipFor(ForumPostReaction::TYPE_LIKE),
                ForumPostReaction::TYPE_DISLIKE => $post->reactionTooltipFor(ForumPostReaction::TYPE_DISLIKE),
            ],
        ];
    }

    private function reactionStatePayload(?ForumPost $firstPost, $posts, $user): array
    {
        $payload = [];
        $allPosts = collect([$firstPost])->filter()->merge($posts->getCollection());

        foreach ($allPosts as $post) {
            $payload[(string) $post->id] = $this->reactionPayload($post, $user);
        }

        return $payload;
    }

    private function buildTopicViewData(ForumTopic $topic, Request $request): array
    {
        $replySort = $this->normalizedReplySort($request->query('sort'));
        $firstPost = $topic->firstPost()
            ->with(['user.avatarMedia', 'reactions.user'])
            ->first();

        $allPosts = ForumPost::query()
            ->with(['user.avatarMedia', 'reactions.user', 'parentPost.user.avatarMedia'])
            ->where('forum_topic_id', $topic->id)
            ->when($firstPost !== null, fn ($query) => $query->where('id', '!=', $firstPost->id))
            ->get();

        $posts = $this->paginateThreadedPosts(
            $this->threadedPosts($allPosts, $firstPost, $replySort),
            $request
        );
        $topicState = $request->user()
            ? ForumTopicUserState::query()
                ->where('forum_topic_id', $topic->id)
                ->where('user_id', (string) $request->user()->id)
                ->first()
            : null;
        $lastCommentPost = $allPosts
            ->sortByDesc(fn (ForumPost $post) => sprintf('%s-%s', optional($post->created_at)?->format('YmdHis.u') ?? '', (string) $post->id))
            ->first();

        return [
            'firstPost' => $firstPost,
            'posts' => $posts,
            'canReply' => $topic->canReply($request->user()),
            'replySort' => $replySort,
            'replyPrefillBody' => old('body', $this->replyPrefillBody($topic, $request)),
            'commentCount' => max(0, $allPosts->count()),
            'lastCommentPost' => $lastCommentPost,
            'lastCommentAuthorName' => $lastCommentPost?->user?->username ?: $lastCommentPost?->user?->getName() ?: 'Deleted user',
            'notificationsEnabled' => (bool) ($topicState?->notifications_enabled ?? false),
        ];
    }

    private function threadedPosts(Collection $posts, ?ForumPost $firstPost, string $replySort): Collection
    {
        $firstPostId = $firstPost ? (string) $firstPost->id : null;
        $sortedPosts = $replySort === 'oldest'
            ? $posts->sortBy([['created_at', 'asc'], ['id', 'asc']])->values()
            : $posts->sortByDesc(fn (ForumPost $post) => sprintf('%s-%s', optional($post->created_at)?->format('YmdHis.u') ?? '', (string) $post->id))->values();
        $sortedPostIds = $sortedPosts->pluck('id')->map(fn ($id) => (string) $id)->all();
        $postsById = $sortedPosts->keyBy(fn (ForumPost $post) => (string) $post->id);

        $resolveDepth = function (?ForumPost $post) use (&$resolveDepth, $postsById, $firstPostId): int {
            if (! $post) {
                return 0;
            }

            if (isset($post->reply_depth) && is_int($post->reply_depth)) {
                return $post->reply_depth;
            }

            $parentId = trim((string) ($post->parent_forum_post_id ?? ''));
            if ($parentId === '' || ($firstPostId !== null && $parentId === $firstPostId)) {
                $post->reply_depth = 1;

                return 1;
            }

            /** @var ForumPost|null $parentPost */
            $parentPost = $postsById->get($parentId) ?? $post->parentPost;
            $post->reply_depth = $resolveDepth($parentPost) + 1;

            return $post->reply_depth;
        };

        $sortedPosts->each(fn (ForumPost $post) => $resolveDepth($post));

        $postsByParent = $sortedPosts->groupBy(function (ForumPost $post) use ($firstPostId): string {
            $parentId = trim((string) ($post->parent_forum_post_id ?? ''));

            if ($parentId === '' || ($firstPostId !== null && $parentId === $firstPostId)) {
                return 'root';
            }

            return $parentId;
        });

        $ordered = collect();
        $visited = [];
        $visit = function (ForumPost $post) use (&$visit, &$ordered, $postsByParent, &$visited): void {
            $postId = (string) $post->id;
            if (isset($visited[$postId])) {
                return;
            }

            $visited[$postId] = true;
            $ordered->push($post);

            foreach ($postsByParent->get($postId, collect()) as $childPost) {
                $visit($childPost);
            }
        };

        foreach ($postsByParent->get('root', collect()) as $rootPost) {
            $visit($rootPost);
        }

        foreach ($sortedPosts as $post) {
            $parentId = trim((string) ($post->parent_forum_post_id ?? ''));
            if ($parentId !== '' && ! in_array($parentId, $sortedPostIds, true) && ($firstPostId === null || $parentId !== $firstPostId)) {
                $visit($post);
            }
        }

        return $ordered;
    }

    private function paginateThreadedPosts(Collection $posts, Request $request, int $perPage = 20): LengthAwarePaginator
    {
        $page = max(1, (int) $request->query('page', 1));
        $total = $posts->count();
        $items = $posts->slice(($page - 1) * $perPage, $perPage)->values();

        return (new LengthAwarePaginator(
            $items,
            $total,
            $perPage,
            $page,
            [
                'path' => $request->url(),
                'query' => $request->query(),
            ]
        ))->onEachSide(1);
    }

    private function buildForumIndexData($user): array
    {
        $categories = ForumCategory::query()
            ->withCount(['topics', 'posts'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ForumCategory $category) => $category->canRead($user))
            ->values();

        $unreadCategoryIds = $user
            ? ForumTopic::unreadForUserQuery($user)
                ->pluck('forum_topics.forum_category_id')
                ->map(fn ($id) => (string) $id)
                ->unique()
                ->values()
                ->all()
            : [];

        return [
            'categories' => $categories,
            'unreadCategoryIds' => $unreadCategoryIds,
        ];
    }

    private function buildForumCategoryData(ForumCategory $category, $user): array
    {
        $topics = ForumTopic::query()
            ->with(['user.avatarMedia', 'lastPostUser.avatarMedia'])
            ->withCount('posts')
            ->where('forum_category_id', $category->id)
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            ->paginate(20)
            ->onEachSide(1);

        $unreadTopicIds = $user
            ? ForumTopic::unreadTopicIdsForUser($user, $topics->getCollection())
            : [];

        $threadCount = (int) ForumTopic::query()
            ->where('forum_category_id', $category->id)
            ->count();
        $postCount = (int) $category->posts()->count();
        $commentCount = max(0, $postCount - $threadCount);
        $viewCount = (int) ForumTopic::query()
            ->where('forum_category_id', $category->id)
            ->sum('view_count');
        $latestTopic = ForumTopic::query()
            ->with('lastPostUser')
            ->where('forum_category_id', $category->id)
            ->whereNotNull('last_post_at')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            ->first();

        return [
            'topics' => $topics,
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestTopic?->last_post_at,
            'latestActivityAuthorName' => $latestTopic?->lastPostUser?->username ?: $latestTopic?->lastPostUser?->getName() ?: 'Deleted user',
        ];
    }

    private function moderationRecipientAddress(): string
    {
        $contact = trim((string) config('mail.contact_to.address', ''));
        if ($contact !== '') {
            return $contact;
        }

        $adminBcc = trim((string) config('mail.admin_bcc', ''));
        if ($adminBcc !== '') {
            return $adminBcc;
        }

        $fallback = trim((string) config('mail.from.address', ''));

        return $fallback !== '' ? $fallback : 'hello@stemmechanics.com.au';
    }
}
