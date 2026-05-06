<?php

namespace App\Http\Controllers;

use App\Helpers;
use App\Contracts\ContentFilter;
use App\Jobs\SendEmail;
use App\Mail\ChildForumActivityNotification;
use App\Mail\ForumPostReport;
use App\Models\ForumCategory;
use App\Models\ClassSession;
use App\Models\ForumPost;
use App\Models\ForumPostAttachment;
use App\Models\ForumPostReaction;
use App\Models\ForumTopic;
use App\Models\ForumTopicUserState;
use App\Models\User;
use App\Support\ForumContent;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ForumController extends Controller
{
    public function __construct(
        private readonly ContentFilter $contentFilter,
    ) {}

    public function index(Request $request): View
    {
        [
            'regularCategories' => $regularCategories,
            'courseCategories' => $courseCategories,
            'unreadCategoryCounts' => $unreadCategoryCounts,
        ] = $this->buildForumIndexData($request->user());

        return view('forum.index', [
            'regularCategories' => $regularCategories,
            'courseCategories' => $courseCategories,
            'unreadCategoryCounts' => $unreadCategoryCounts,
        ]);
    }

    public function feed(): Response
    {
        $publicCategoryIds = ForumCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ForumCategory $category) => ! $category->isDivider() && $category->canRead(null))
            ->pluck('id')
            ->all();

        $topics = ForumTopic::query()
            ->with([
                'category',
                'firstPost.user',
                'user',
            ])
            ->withCount('posts')
            ->where('is_approved', true)
            ->whereIn('forum_category_id', $publicCategoryIds)
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(function (ForumTopic $topic): array {
                $firstPost = $topic->firstPost;
                $updatedAt = $topic->last_post_at ?? $topic->created_at ?? now();
                $replyCount = max(0, (int) $topic->posts_count - 1);
                $excerpt = $this->forumFeedExcerpt($firstPost ? (string) $firstPost->body : '');

                return [
                    'author' => trim((string) ($topic->user?->forumDisplayName() ?? 'deleted')),
                    'category' => trim((string) $topic->category->name),
                    'description' => implode("\n\n", array_filter([
                        $excerpt,
                        $this->forumFeedMetaLine($topic, $replyCount),
                    ])),
                    'excerpt' => $excerpt,
                    'enclosure' => $this->forumFeedEnclosure($topic),
                    'guid' => route('forum.topic.show', [
                        'categorySlug' => (string) $topic->category->slug,
                        'topicSlug' => (string) $topic->slug,
                    ]),
                    'link' => route('forum.topic.show', [
                        'categorySlug' => (string) $topic->category->slug,
                        'topicSlug' => (string) $topic->slug,
                    ]),
                    'locked' => $topic->is_locked ? 'true' : 'false',
                    'pinned' => $topic->is_pinned ? 'true' : 'false',
                    'pubDate' => $updatedAt,
                    'replyCount' => $replyCount,
                    'title' => $topic->plainTitle(),
                    'updatedAt' => $updatedAt,
                ];
            });

        return response()
            ->view('forum.feed', [
                'generatedAt' => now(),
                'items' => $topics,
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }

    public function showCategory(Request $request, string $categorySlug): View|RedirectResponse
    {
        $category = $this->findCategoryOrFail($categorySlug);
        $category->loadMissing('classSession');
        abort_if($category->isDivider(), 404);
        $user = $request->user();
        $topicSort = $this->normalizedTopicSort($request->query('topicSort'), $category);

        if ($response = $this->forumReadAccessWebResponse($request, $category->canRead($user), 'discussion category')) {
            return $response;
        }

        [
            'topics' => $topics,
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestActivityAt,
            'latestActivityAuthorName' => $latestActivityAuthorName,
        ] = $this->buildForumCategoryData($category, $user, $topicSort);

        return view('forum.category', [
            'category' => $category,
            'topics' => $topics,
            'topicSort' => $topicSort,
            'canWrite' => $this->canCreateTopic($user, $category),
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
        abort_unless($this->canCreateTopic($request->user(), $category), 403);

        return view('forum.topic-create', [
            'category' => $category,
        ]);
    }

    public function storeTopic(Request $request, string $categorySlug): RedirectResponse
    {
        $category = $this->findCategoryOrFail($categorySlug);
        abort_if($category->isDivider(), 404);
        abort_unless($this->canCreateTopic($request->user(), $category), 403);
        $topicSort = trim((string) $request->query('topicSort', ''));
        $topicSortQuery = $topicSort !== '' ? ['topicSort' => $topicSort] : [];

        $validated = $request->validate(array_merge([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string'],
        ], $this->forumAttachmentValidationRules($request->user())));

        $body = ForumContent::normalize((string) $validated['body']);
        if (! ForumContent::hasMeaningfulContent($body)) {
            return back()
                ->withErrors(['body' => 'Post content cannot be empty.'])
                ->withInput();
        }

        $title = trim((string) $validated['title']);
        $plainTitle = ForumContent::plainTitleMarkdown($title);
        if ($plainTitle === '') {
            return back()
                ->withErrors(['title' => 'Thread title cannot be empty.'])
                ->withInput();
        }

        $titleFilter = $this->contentFilter->inspect($plainTitle, 'forum');
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

        $author = $request->user();
        $requiresApproval = $author?->childForumTopicRequiresApproval() ?? false;
        /** @var ForumTopic|null $topic */
        $topic = null;
        /** @var ForumPost|null $post */
        $post = null;

        DB::transaction(function () use ($category, $author, $requiresApproval, $title, $body, $validated, &$topic, &$post): void {
            $topic = new ForumTopic();
            $topic->forum_category_id = (string) $category->id;
            $topic->user_id = (string) $author->id;
            $topic->title = $title;
            $topic->slug = ForumTopic::generateUniqueSlug($topic->title, (string) $category->id);
            $topic->last_post_at = now();
            $topic->last_post_user_id = (string) $author->id;
            $topic->is_approved = ! $requiresApproval;
            $topic->save();

            $post = new ForumPost();
            $post->forum_topic_id = (string) $topic->id;
            $post->user_id = (string) $author->id;
            $post->is_topic_starter = true;
            $post->is_approved = ! $requiresApproval;
            $post->body = $body;
            $post->save();

            $this->saveForumPostAttachments($post, $validated['attachments'] ?? [], $author);
        });

        if ($requiresApproval) {
            $this->notifyParentOfChildForumActivity(
                $author,
                'thread',
                'submitted',
                $category,
                $topic,
                $body,
                true,
                $topic
            );

            session()->flash('message', 'Your thread has been submitted for parent approval.');
            session()->flash('message-title', 'Approval required');
            session()->flash('message-type', 'info');

            return redirect()->route('forum.category.show', array_merge(['categorySlug' => $category->slug], $topicSortQuery));
        }

        $this->ensureTopicNotificationsEnabledForPoster($topic, $author);
        $this->markTopicRead($topic, $author, $post->created_at);
        $this->notifyParentOfChildForumActivity(
            $author,
            'thread',
            'posted',
            $category,
            $topic,
            $body
        );

        session()->flash('message', 'Your thread has been saved.');
        session()->flash('message-title', 'Thread created');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ] + $topicSortQuery).'#post-'.$post->id);
    }

    public function showTopic(Request $request, string $categorySlug, string $topicSlug): View|RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();

        if ($response = $this->forumReadAccessWebResponse($request, $topic->canRead($user), 'discussion thread')) {
            return $response;
        }

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

    public function updateTitle(Request $request, string $categorySlug, string $topicSlug): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();
        abort_unless($topic->canRead($user), 403);
        abort_unless($topic->canEditTitle($user), 403);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
        ]);

        $title = trim((string) $validated['title']);
        $plainTitle = ForumContent::plainTitleMarkdown($title);
        if ($plainTitle === '') {
            return back()
                ->withErrors(['title' => 'Thread title cannot be empty.'])
                ->withInput();
        }

        $titleFilter = $this->contentFilter->inspect($plainTitle, 'forum');
        if ($titleFilter->blocked) {
            return back()
                ->withErrors(['title' => $titleFilter->message ?? 'Thread title is not allowed.'])
                ->withInput();
        }

        $topic->title = $title;
        $topic->slug = ForumTopic::generateUniqueSlug($title, (string) $topic->forum_category_id, (string) $topic->id);
        $topic->save();

        session()->flash('message', 'Thread title updated.');
        session()->flash('message-title', 'Thread updated');
        session()->flash('message-type', 'success');

        return redirect()->to(route('forum.topic.show', [
            'categorySlug' => $topic->category->slug,
            'topicSlug' => $topic->slug,
            'sort' => $this->normalizedReplySort($request->query('sort')),
        ]));
    }

    public function storePost(Request $request, string $categorySlug, string $topicSlug): RedirectResponse|JsonResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless($topic->canReply($request->user()), 403);

        $validated = $request->validate(array_merge([
            'body' => ['required', 'string'],
            'reply_to_post_id' => ['nullable', 'string'],
        ], $this->forumAttachmentValidationRules($request->user())));

        $body = ForumContent::normalize((string) $validated['body']);
        if (! ForumContent::hasMeaningfulContent($body)) {
            throw ValidationException::withMessages([
                'body' => 'Reply content cannot be empty.',
            ]);
        }

        $bodyFilter = $this->contentFilter->inspect($body, 'forum');
        if ($bodyFilter->blocked) {
            throw ValidationException::withMessages([
                'body' => $bodyFilter->message ?? 'Reply content is not allowed.',
            ]);
        }

        $author = $request->user();
        $requiresApproval = $author?->childForumReplyRequiresApproval() ?? false;
        /** @var ForumPost|null $post */
        $post = null;

        DB::transaction(function () use ($topic, $author, $requiresApproval, $body, $validated, &$post): void {
            $post = new ForumPost();
            $post->forum_topic_id = (string) $topic->id;
            $post->parent_forum_post_id = $this->validatedReplyParentId($topic, $validated['reply_to_post_id'] ?? null);
            $post->user_id = (string) $author->id;
            $post->is_approved = ! $requiresApproval;
            $post->body = $body;
            $post->save();

            $this->saveForumPostAttachments($post, $validated['attachments'] ?? [], $author);
        });

        if ($requiresApproval) {
            $this->notifyParentOfChildForumActivity(
                $author,
                'reply',
                'submitted',
                $topic->category,
                $topic,
                $body,
                true,
                $post
            );

            session()->flash('message', 'Your reply has been submitted for parent approval.');
            session()->flash('message-title', 'Approval required');
            session()->flash('message-type', 'info');

            if ($request->expectsJson()) {
                return response()->json([
                    'redirect' => route('forum.topic.show', [
                        'categorySlug' => $topic->category->slug,
                        'topicSlug' => $topic->slug,
                        'sort' => $this->normalizedReplySort($request->query('sort')),
                    ]),
                    'message' => 'Your reply has been submitted for parent approval.',
                ]);
            }

            return redirect()->to(route('forum.topic.show', [
                'categorySlug' => $topic->category->slug,
                'topicSlug' => $topic->slug,
                'sort' => $this->normalizedReplySort($request->query('sort')),
            ]));
        }

        $topic->last_post_at = $post->created_at;
        $topic->last_post_user_id = (string) $author->id;
        $topic->save();
        $this->ensureTopicNotificationsEnabledForPoster($topic, $author);
        $this->markTopicRead($topic, $author, $post->created_at);
        $this->notifyParentOfChildForumActivity(
            $author,
            'reply',
            'posted',
            $topic->category,
            $topic,
            $body
        );

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

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('forum.topic.show', $redirectParams).'#post-'.$post->id,
                'message' => 'Reply posted.',
            ]);
        }

        return redirect()->to(route('forum.topic.show', $redirectParams).'#post-'.$post->id);
    }

    public function updatePost(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost): RedirectResponse|JsonResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);
        abort_unless($forumPost->is_approved && ! $forumPost->isDeleted(), 404);
        abort_unless($forumPost->canEdit($request->user()), 403);

        $validated = $request->validate(array_merge([
            'body' => ['required', 'string'],
            'modal_mode' => ['nullable', 'string'],
            'edit_post_id' => ['nullable', 'string'],
            'removed_attachments' => ['nullable', 'array'],
            'removed_attachments.*' => ['string'],
        ], $this->forumAttachmentValidationRules($request->user())));

        $body = ForumContent::normalize((string) $validated['body']);
        if (! ForumContent::hasMeaningfulContent($body)) {
            throw ValidationException::withMessages([
                'body' => 'Post content cannot be empty.',
            ]);
        }

        $bodyFilter = $this->contentFilter->inspect($body, 'forum');
        if ($bodyFilter->blocked) {
            throw ValidationException::withMessages([
                'body' => $bodyFilter->message ?? 'Post content is not allowed.',
            ]);
        }

        $attachmentsAdded = 0;
        $attachmentsRemoved = 0;

        DB::transaction(function () use ($forumPost, $body, $validated, &$attachmentsAdded, &$attachmentsRemoved, $request): void {
            if ($forumPost->body !== $body) {
                $forumPost->body = $body;
            }

            $attachmentsRemoved = $this->removeForumPostAttachments($forumPost, $validated['removed_attachments'] ?? []);
            $attachmentsAdded = $this->saveForumPostAttachments($forumPost, $validated['attachments'] ?? [], $request->user());

            if ($forumPost->isDirty('body') || $attachmentsRemoved > 0 || $attachmentsAdded > 0) {
                $forumPost->edited_at = now();
                $forumPost->save();
            }
        });

        session()->flash('message', 'Post updated.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        if ($request->expectsJson()) {
            return response()->json([
                'redirect' => route('forum.topic.show', [
                    'categorySlug' => $topic->category->slug,
                    'topicSlug' => $topic->slug,
                    'sort' => $this->normalizedReplySort($request->query('sort')),
                ]).'#post-'.$forumPost->id,
                'message' => 'Post updated.',
            ]);
        }

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
        abort_if(! $forumPost->is_approved || $forumPost->isDeleted(), 404);

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
        abort_if(! $forumPost->is_approved || $forumPost->isDeleted(), 404);

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
        [
            'regularCategories' => $regularCategories,
            'courseCategories' => $courseCategories,
            'unreadCategoryCounts' => $unreadCategoryCounts,
        ] = $this->buildForumIndexData($request->user());

        return response()->json([
            'categoriesHtml' => $regularCategories->isEmpty() && $courseCategories->isEmpty()
                ? ''
                : view('forum.partials.category-groups', [
                    'regularCategories' => $regularCategories,
                    'courseCategories' => $courseCategories,
                    'unreadCategoryCounts' => $unreadCategoryCounts,
                ])->render(),
        ]);
    }

    public function categorySnapshot(Request $request, string $categorySlug): JsonResponse
    {
        $category = $this->findCategoryOrFail($categorySlug);
        abort_if($category->isDivider(), 404);
        $user = $request->user();
        $topicSort = $this->normalizedTopicSort($request->query('topicSort'), $category);

        if ($response = $this->forumReadAccessJsonResponse($request, $category->canRead($user), 'discussion category')) {
            return $response;
        }

        [
            'topics' => $topics,
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestActivityAt,
            'latestActivityAuthorName' => $latestActivityAuthorName,
        ] = $this->buildForumCategoryData($category, $user, $topicSort);

        return response()->json([
            'threadsHtml' => $topics->isEmpty()
                ? ''
                : view('forum.partials.thread-list', [
                    'topics' => $topics,
                    'category' => $category,
                    'unreadTopicLookup' => array_flip($unreadTopicIds),
                    'topicSort' => $topicSort,
                ])->render(),
            'paginationHtml' => $topics->appends($request->query())->links()->toHtml(),
            'emptyText' => $topics->isEmpty()
                ? 'No threads have been created in this category yet.'
                : '',
        ]);
    }

    public function topicSnapshot(Request $request, string $categorySlug, string $topicSlug): JsonResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();

        if ($response = $this->forumReadAccessJsonResponse($request, $topic->canRead($user), 'discussion thread')) {
            return $response;
        }

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
                'replySort' => $topicView['replySort'],
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
        abort_unless($topic->canRead($user), 403);

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
        $user = $request->user();
        abort_unless(
            $user?->isAdmin() || (string) $topic->user_id === (string) $user?->id,
            403
        );

        $redirectUrl = route('forum.category.show', $topic->category->slug);
        $this->deleteForumTopicAttachments($topic);
        $topic->delete();

        session()->flash('message', 'Thread deleted.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->to($redirectUrl);
    }

    public function destroyPost(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost): RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);
        $user = $request->user();
        abort_unless(
            $user?->isAdmin() || (string) $forumPost->user_id === (string) $user?->id,
            403
        );
        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);

        /** @var ForumPost|null $firstPost */
        $firstPost = $topic->firstPost()->first();
        if ($firstPost && (string) $forumPost->id === (string) $firstPost->id) {
            session()->flash('message', 'Delete the thread to remove its first post.');
            session()->flash('message-title', 'Post not deleted');
            session()->flash('message-type', 'danger');

            return back();
        }

        $this->deleteForumPostAttachments($forumPost);
        $forumPost->softDeleteToPlaceholder();
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

    public function downloadAttachment(Request $request, string $categorySlug, string $topicSlug, ForumPost $forumPost, ForumPostAttachment $attachment): StreamedResponse|RedirectResponse
    {
        $topic = $this->findTopicOrFail($categorySlug, $topicSlug);

        if ($response = $this->forumReadAccessWebResponse($request, $topic->canRead($request->user()), 'discussion thread')) {
            return $response;
        }

        abort_unless((string) $forumPost->forum_topic_id === (string) $topic->id, 404);
        abort_unless($forumPost->is_approved && ! $forumPost->isDeleted(), 404);
        abort_unless((string) $attachment->forum_post_id === (string) $forumPost->id, 404);
        abort_unless($forumPost->attachments->contains(fn (ForumPostAttachment $postAttachment): bool => (string) $postAttachment->id === (string) $attachment->id), 404);
        abort_unless(Storage::disk('local')->exists($attachment->storage_path), 404);

        return Storage::disk('local')->download(
            $attachment->storage_path,
            $attachment->downloadFileName()
        );
    }

    private function findCategoryOrFail(string $slug): ForumCategory
    {
        return ForumCategory::query()
            ->where('slug', ForumCategory::normalizeSlug($slug))
            ->firstOrFail();
    }

    private function forumReadAccessWebResponse(Request $request, bool $canRead, string $resource): ?RedirectResponse
    {
        if ($canRead) {
            return null;
        }

        if (! $request->user()) {
            return redirect()->guest(route('login'));
        }

        session()->flash('message', 'You do not have access to this '.$resource.'.');
        session()->flash('message-title', 'Access denied');
        session()->flash('message-type', 'warning');

        return redirect()->route('forum.index');
    }

    private function forumReadAccessJsonResponse(Request $request, bool $canRead, string $resource): ?JsonResponse
    {
        if ($canRead) {
            return null;
        }

        if (! $request->user()) {
            return response()->json([
                'message' => 'Authentication is required to access this '.$resource.'.',
            ], 401);
        }

        return response()->json([
            'message' => 'You do not have access to this '.$resource.'.',
        ], 403);
    }

    private function findTopicOrFail(string $categorySlug, string $topicSlug): ForumTopic
    {
        $category = $this->findCategoryOrFail($categorySlug);
        $normalizedSlug = trim((string) Str::slug($topicSlug));

        return ForumTopic::query()
            ->with(['category', 'user', 'lastPostUser'])
            ->where('forum_category_id', $category->id)
            ->where('slug', $normalizedSlug)
            ->where('is_approved', true)
            ->firstOrFail();
    }

    private function normalizedReplySort(mixed $value): string
    {
        return strtolower(trim((string) $value)) === 'latest' ? 'latest' : 'oldest';
    }

    private function normalizedTopicSort(mixed $value, ForumCategory $category): string
    {
        $default = $category->classSession ? 'oldest' : 'latest';
        $normalized = strtolower(trim((string) $value));

        return in_array($normalized, ['latest', 'oldest'], true) ? $normalized : $default;
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

    /**
     * @return array<string, array<int, string>>
     */
    private function forumAttachmentValidationRules(?User $user): array
    {
        $maxSize = max((int) ceil(Helpers::getMaxUploadSize($user) / 1024), 1);

        return [
            'attachments' => ['nullable', 'array'],
            'attachments.*' => ['file', 'max:'.$maxSize],
        ];
    }

    /**
     * @param  array<int, mixed>  $files
     */
    private function saveForumPostAttachments(ForumPost $post, array $files, ?User $author): int
    {
        $savedCount = 0;
        $storedPaths = [];
        $nextSortOrder = (int) ($post->attachments()->max('sort_order') ?? 0);

        try {
            foreach ($files as $file) {
                if (! $file instanceof UploadedFile) {
                    continue;
                }

                $originalFilename = trim((string) $file->getClientOriginalName());
                $safeFilename = Helpers::cleanFileName($originalFilename);
                if ($safeFilename === '') {
                    $safeFilename = 'attachment';
                }

                $directory = 'forum-post-attachments/'.(string) $post->id;
                $storedFilename = Str::uuid()->toString().'-'.$safeFilename;
                $storedPath = Storage::disk('local')->putFileAs($directory, $file, $storedFilename);

                if (! is_string($storedPath) || $storedPath === '') {
                    throw new \RuntimeException('Attachment upload failed.');
                }

                $storedPaths[] = $storedPath;

                ForumPostAttachment::query()->create([
                    'forum_post_id' => (string) $post->id,
                    'uploaded_by_user_id' => $author?->id,
                    'original_filename' => $originalFilename !== '' ? $originalFilename : $safeFilename,
                    'storage_path' => $storedPath,
                    'mime_type' => $file->getClientMimeType() ?: null,
                    'size_bytes' => (int) $file->getSize(),
                    'sort_order' => ++$nextSortOrder,
                ]);

                $savedCount++;
            }
        } catch (\Throwable $e) {
            if ($storedPaths !== []) {
                Storage::disk('local')->delete($storedPaths);
            }

            throw $e;
        }

        return $savedCount;
    }

    private function deleteForumPostAttachments(ForumPost $post): void
    {
        $directory = 'forum-post-attachments/'.(string) $post->id;
        Storage::disk('local')->deleteDirectory($directory);
        ForumPostAttachment::query()
            ->where('forum_post_id', $post->id)
            ->delete();
    }

    /**
     * @param  array<int, mixed>  $attachmentIds
     */
    private function removeForumPostAttachments(ForumPost $post, array $attachmentIds): int
    {
        $normalizedIds = collect($attachmentIds)
            ->map(fn ($attachmentId): string => (string) $attachmentId)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($normalizedIds === []) {
            return 0;
        }

        $attachments = ForumPostAttachment::query()
            ->where('forum_post_id', $post->id)
            ->whereIn('id', $normalizedIds)
            ->get(['id', 'storage_path']);

        if ($attachments->isEmpty()) {
            return 0;
        }

        $storagePaths = $attachments->pluck('storage_path')->filter()->values()->all();
        $removedCount = $attachments->count();

        ForumPostAttachment::query()
            ->where('forum_post_id', $post->id)
            ->whereIn('id', $attachments->pluck('id')->all())
            ->delete();

        if ($storagePaths !== []) {
            DB::afterCommit(static function () use ($storagePaths): void {
                Storage::disk('local')->delete($storagePaths);
            });
        }

        return $removedCount;
    }

    private function deleteForumTopicAttachments(ForumTopic $topic): void
    {
        ForumPost::query()
            ->where('forum_topic_id', $topic->id)
            ->select(['id'])
            ->get()
            ->each(function (ForumPost $post): void {
                $this->deleteForumPostAttachments($post);
            });
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
            ->where('is_approved', true)
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
        /** @var ForumPost|null $firstPost */
        $firstPost = $topic->firstPost()
            ->with(['user.avatarMedia', 'reactions.user', 'attachments'])
            ->first();

        /** @var Collection<int, ForumPost> $allPosts */
        $allPosts = ForumPost::query()
            ->with(['user.avatarMedia', 'reactions.user', 'parentPost.user.avatarMedia', 'attachments'])
            ->where('forum_topic_id', $topic->id)
            ->where('is_approved', true)
            ->when($firstPost !== null, function ($query) use ($firstPost) {
                return $query->where('id', '!=', $firstPost->id);
            })
            ->get();

        $posts = $this->paginateThreadedPosts(
            $this->threadedPosts($allPosts, $firstPost, $replySort),
            $request
        );
        /** @var ForumTopicUserState|null $topicState */
        $topicState = $request->user()
            ? ForumTopicUserState::query()
                ->where('forum_topic_id', $topic->id)
                ->where('user_id', (string) $request->user()->id)
                ->first()
            : null;
        /** @var ForumPost|null $lastCommentPost */
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
            'lastCommentAuthorName' => $lastCommentPost?->user?->forumDisplayName() ?: 'deleted',
            'notificationsEnabled' => (bool) $topicState?->notifications_enabled,
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

            if ($post->reply_depth !== null) {
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
            ->with('classSession')
            ->withCount(['topics', 'posts'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->filter(fn (ForumCategory $category) => $category->canRead($user))
            ->values();

        $unreadCategoryCounts = $user ? ForumTopic::unreadCountMapForUser($user) : [];
        $courseCategories = $categories
            ->filter(fn (ForumCategory $category) => $category->classSession !== null)
            ->sortByDesc(function (ForumCategory $category): int {
                $classSession = $category->classSession;
                if (! $classSession instanceof ClassSession || $classSession->starts_at === null) {
                    return 0;
                }

                return (int) $classSession->starts_at->timestamp;
            })
            ->values();
        $regularCategories = $categories
            ->reject(fn (ForumCategory $category) => $category->classSession !== null)
            ->values();

        return [
            'regularCategories' => $regularCategories,
            'courseCategories' => $courseCategories,
            'unreadCategoryCounts' => $unreadCategoryCounts,
        ];
    }

    private function forumFeedExcerpt(string $body): string
    {
        $excerpt = trim((string) ForumContent::emailPreviewText($body));
        if ($excerpt === '') {
            return '';
        }

        return Str::limit($excerpt, 240);
    }

    private function forumFeedMetaLine(ForumTopic $topic, int $replyCount): string
    {
        $parts = [];

        $categoryName = trim((string) $topic->category->name);
        if ($categoryName !== '') {
            $parts[] = $categoryName;
        }

        $authorName = trim((string) ($topic->user?->forumDisplayName() ?? ''));
        if ($authorName !== '') {
            $parts[] = 'by '.$authorName;
        }

        $parts[] = $replyCount.' '.Str::plural('reply', $replyCount);

        if ($topic->is_pinned) {
            $parts[] = 'pinned';
        }

        if ($topic->is_locked) {
            $parts[] = 'locked';
        }

        return implode(' · ', $parts);
    }

    private function forumFeedEnclosure(ForumTopic $topic): ?array
    {
        $firstPost = $topic->firstPost;
        if (! $firstPost) {
            return null;
        }

        $attachment = $firstPost->attachments
            ->first(function (ForumPostAttachment $postAttachment): bool {
                $mimeType = trim((string) ($postAttachment->mime_type ?? ''));

                return $mimeType !== '' && str_starts_with(strtolower($mimeType), 'image/');
            });

        if (! $attachment instanceof ForumPostAttachment) {
            return null;
        }

        return [
            'url' => route('forum.post.attachment.download', [
                'categorySlug' => (string) $topic->category->slug,
                'topicSlug' => (string) $topic->slug,
                'forumPost' => $firstPost->id,
                'attachment' => $attachment->id,
            ]),
            'type' => trim((string) $attachment->mime_type),
        ];
    }

    private function buildForumCategoryData(ForumCategory $category, $user, string $topicSort): array
    {
        $topicsQuery = ForumTopic::query()
            ->with(['user.avatarMedia', 'lastPostUser.avatarMedia'])
            ->withCount('posts')
            ->where('forum_category_id', $category->id)
            ->where('is_approved', true);

        if ($topicSort === 'oldest') {
            $topicsQuery
                ->orderBy('created_at')
                ->orderBy('id');
        } else {
            $topicsQuery
                ->orderByDesc('is_pinned')
                ->orderByDesc('last_post_at')
                ->orderByDesc('created_at')
                ->orderByDesc('id');
        }

        $topics = $topicsQuery
            ->paginate(20)
            ->onEachSide(1);

        $unreadTopicIds = $user
            ? ForumTopic::unreadTopicIdsForUser($user, $topics->getCollection())
            : [];

        $threadCount = (int) ForumTopic::query()
            ->where('forum_category_id', $category->id)
            ->where('is_approved', true)
            ->count();
        $postCount = (int) $category->posts()->count();
        $commentCount = max(0, $postCount - $threadCount);
        $viewCount = (int) ForumTopic::query()
            ->where('forum_category_id', $category->id)
            ->sum('view_count');
        /** @var ForumTopic|null $latestTopic */
        $latestTopic = ForumTopic::query()
            ->with('lastPostUser')
            ->where('forum_category_id', $category->id)
            ->where('is_approved', true)
            ->whereNotNull('last_post_at')
            ->orderByDesc('last_post_at')
            ->orderByDesc('created_at')
            ->first();

        return [
            'topics' => $topics,
            'topicSort' => $topicSort,
            'unreadTopicIds' => $unreadTopicIds,
            'threadCount' => $threadCount,
            'commentCount' => $commentCount,
            'viewCount' => $viewCount,
            'latestActivityAt' => $latestTopic?->last_post_at,
            'latestActivityAuthorName' => $latestTopic?->lastPostUser?->forumDisplayName() ?: 'deleted',
        ];
    }

    private function canCreateTopic($user, ForumCategory $category): bool
    {
        if (! $category->canWrite($user)) {
            return false;
        }

        return $user?->canCreateForumTopics() ?? false;
    }

    private function notifyParentOfChildForumActivity($child, string $activityLabel, string $statusLabel, ForumCategory $category, ForumTopic $topic, string $preview, bool $forceNotification = false, ForumTopic|ForumPost|null $approvalTarget = null): void
    {
        if (! $child || ! $child->isChildAccount()) {
            return;
        }

        $shouldNotify = $forceNotification || ($activityLabel === 'thread'
            ? $child->parentShouldBeNotifiedOnForumTopics()
            : $child->parentShouldBeNotifiedOnForumReplies());

        $parent = $child->parent;
        if (! $shouldNotify || ! $parent || ! $parent->canReceiveEmail()) {
            return;
        }

        $approveUrl = $statusLabel === 'submitted'
            ? $this->pendingChildForumApprovalUrl($child, $activityLabel, $approvalTarget)
            : null;

        dispatch(new SendEmail(
            $parent->email,
            new ChildForumActivityNotification(
                $parent->getName(),
                $child->forumDisplayName(),
                $activityLabel,
                $statusLabel,
                (string) $category->name,
                $topic->plainTitle(),
                mb_substr(trim(ForumContent::emailPreviewText($preview)), 0, 300),
                route('account.children.approvals').'#child-'.$child->id,
                $approveUrl
            )
        ))->onQueue('mail');
    }

    private function pendingChildForumApprovalUrl($child, string $activityLabel, ForumTopic|ForumPost|null $approvalTarget): ?string
    {
        if (! $child || ! $child->isChildAccount() || $approvalTarget === null) {
            return null;
        }

        $expiresAt = now()->addDays(14);

        if ($activityLabel === 'thread' && $approvalTarget instanceof ForumTopic) {
            return URL::temporarySignedRoute('account.children.topic.approve-link', $expiresAt, [
                'child' => $child,
                'forumTopic' => $approvalTarget,
            ]);
        }

        if ($activityLabel === 'reply' && $approvalTarget instanceof ForumPost) {
            return URL::temporarySignedRoute('account.children.post.approve-link', $expiresAt, [
                'child' => $child,
                'forumPost' => $approvalTarget,
            ]);
        }

        return null;
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
