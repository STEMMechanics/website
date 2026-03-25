<?php

namespace App\Http\Controllers;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Media;
use App\Models\User;
use App\Rules\UsernameRule;
use App\Support\ForumContent;
use App\Support\UserAnonymizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ChildAccountController extends Controller
{
    public function __construct(
        private readonly UserAnonymizer $userAnonymizer
    ) {}

    public function create(Request $request): View
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);

        return view('account.children.edit', [
            'child' => new User([
                'child_can_create_forum_topics' => true,
                'child_can_reply_in_forum' => true,
                'child_forum_topic_requires_approval' => false,
                'child_forum_reply_requires_approval' => false,
                'child_parent_notified_on_forum_topics' => false,
                'child_parent_notified_on_forum_replies' => false,
                'child_can_select_avatar_media' => true,
                'child_can_use_avatar_camera' => true,
            ]),
            'isNew' => true,
            'pendingTopics' => collect(),
            'pendingReplies' => collect(),
        ]);
    }

    public function approvals(Request $request): View
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);

        return view('account.children.approvals', $this->pendingForumDataForParent($parent));
    }

    public function store(Request $request): RedirectResponse
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:32', 'unique:users,username', new UsernameRule(false)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'child_can_create_forum_topics' => ['nullable', 'boolean'],
            'child_can_reply_in_forum' => ['nullable', 'boolean'],
            'child_forum_topic_requires_approval' => ['nullable', 'boolean'],
            'child_forum_reply_requires_approval' => ['nullable', 'boolean'],
            'child_parent_notified_on_forum_topics' => ['nullable', 'boolean'],
            'child_parent_notified_on_forum_replies' => ['nullable', 'boolean'],
            'child_can_select_avatar_media' => ['nullable', 'boolean'],
            'child_can_use_avatar_camera' => ['nullable', 'boolean'],
            ...$this->avatarValidationRules($parent),
        ]);

        $child = new User();
        $child->parent_user_id = (string) $parent->id;
        $child->username = User::normalizeUsername((string) $validated['username']);
        $child->password = (string) $validated['password'];
        $child->email = null;
        $child->email_verified_at = null;
        $this->fillDiscussionPermissions($child, $request, true);
        if (User::hasDatabaseColumn('child_can_select_avatar_media')) {
            $child->child_can_select_avatar_media = $request->boolean('child_can_select_avatar_media', true);
        }
        if (User::hasDatabaseColumn('child_can_use_avatar_camera')) {
            $child->child_can_use_avatar_camera = $request->boolean('child_can_use_avatar_camera', true);
        }
        if (
            User::hasDatabaseColumn('child_can_select_avatar_media')
            && User::hasDatabaseColumn('child_can_use_avatar_camera')
            && ! $child->child_can_select_avatar_media
        ) {
            $child->child_can_use_avatar_camera = false;
        }
        $this->fillAvatarSettings($child, $validated);
        $child->save();

        session()->flash('message', 'Child account created.');
        session()->flash('message-title', 'Child account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.show');
    }

    public function edit(Request $request, User $child): View
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);

        return view('account.children.edit', [
            'child' => $child,
            'isNew' => false,
            ...$this->pendingForumData($child),
        ]);
    }

    public function update(Request $request, User $child): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);

        $validated = $request->validate([
            'username' => ['required', 'string', 'max:32', 'unique:users,username,'.$child->id, new UsernameRule(false)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'child_can_create_forum_topics' => ['nullable', 'boolean'],
            'child_can_reply_in_forum' => ['nullable', 'boolean'],
            'child_forum_topic_requires_approval' => ['nullable', 'boolean'],
            'child_forum_reply_requires_approval' => ['nullable', 'boolean'],
            'child_parent_notified_on_forum_topics' => ['nullable', 'boolean'],
            'child_parent_notified_on_forum_replies' => ['nullable', 'boolean'],
            'child_can_select_avatar_media' => ['nullable', 'boolean'],
            'child_can_use_avatar_camera' => ['nullable', 'boolean'],
            ...$this->avatarValidationRules($parent),
        ]);

        $child->username = User::normalizeUsername((string) $validated['username']);
        $this->fillDiscussionPermissions($child, $request);
        if (User::hasDatabaseColumn('child_can_select_avatar_media')) {
            $child->child_can_select_avatar_media = $request->boolean('child_can_select_avatar_media');
        }
        if (User::hasDatabaseColumn('child_can_use_avatar_camera')) {
            $child->child_can_use_avatar_camera = $request->boolean('child_can_use_avatar_camera');
        }
        if (
            User::hasDatabaseColumn('child_can_select_avatar_media')
            && User::hasDatabaseColumn('child_can_use_avatar_camera')
            && ! $child->child_can_select_avatar_media
        ) {
            $child->child_can_use_avatar_camera = false;
        }
        $this->fillAvatarSettings($child, $validated);

        if (trim((string) ($validated['password'] ?? '')) !== '') {
            $child->password = (string) $validated['password'];
        }

        $child->save();

        session()->flash('message', 'Child account updated.');
        session()->flash('message-title', 'Child account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.show');
    }

    public function destroy(Request $request, User $child): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);

        $this->userAnonymizer->anonymize(
            $child,
            $request->boolean('delete_discussion_threads'),
            false
        );

        session()->flash('message', 'Child account deleted.');
        session()->flash('message-title', 'Child account removed');
        session()->flash('message-type', 'success');

        return redirect()->route('account.show');
    }

    public function bulkUpdateApprovals(Request $request): RedirectResponse
    {
        $parent = $request->user();
        abort_unless($parent && $parent->isFullAccount(), 403);

        $validated = $request->validate([
            'action' => ['required', Rule::in(['approve', 'reject'])],
            'selected_items' => ['nullable', 'array'],
            'selected_items.*' => ['string', 'max:120'],
        ]);

        $selectedItems = collect($validated['selected_items'] ?? [])
            ->map(fn ($item) => trim((string) $item))
            ->filter(fn ($item) => $item !== '')
            ->unique()
            ->values();

        if ($selectedItems->isEmpty()) {
            session()->flash('message', 'Select at least one pending item first.');
            session()->flash('message-title', 'Nothing selected');
            session()->flash('message-type', 'warning');

            return redirect()->route('account.children.approvals');
        }

        [$topicIds, $postIds] = $this->parseSelectedApprovalItems($selectedItems->all());
        $childIds = $parent->children()
            ->whereNull('anonymized_at')
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        $pendingTopics = ForumTopic::query()
            ->whereIn('id', $topicIds)
            ->whereIn('user_id', $childIds)
            ->where('is_approved', false)
            ->get();

        $pendingReplies = ForumPost::query()
            ->with('topic')
            ->whereIn('id', $postIds)
            ->whereIn('user_id', $childIds)
            ->where('is_approved', false)
            ->whereHas('topic', fn ($query) => $query->where('is_approved', true))
            ->get();

        $processedCount = 0;

        DB::transaction(function () use ($validated, $parent, $pendingTopics, $pendingReplies, &$processedCount): void {
            if ($validated['action'] === 'approve') {
                foreach ($pendingTopics as $topic) {
                    $this->approvePendingTopic($parent, $topic);
                    $processedCount++;
                }

                foreach ($pendingReplies as $reply) {
                    $this->approvePendingPost($parent, $reply);
                    $processedCount++;
                }

                return;
            }

            foreach ($pendingTopics as $topic) {
                $this->rejectPendingTopic($topic);
                $processedCount++;
            }

            foreach ($pendingReplies as $reply) {
                $this->rejectPendingPost($reply);
                $processedCount++;
            }
        });

        if ($processedCount === 0) {
            session()->flash('message', 'Those items are no longer waiting for approval.');
            session()->flash('message-title', 'Nothing changed');
            session()->flash('message-type', 'warning');

            return redirect()->route('account.children.approvals');
        }

        $actionLabel = $validated['action'] === 'approve' ? 'approved' : 'discarded';
        session()->flash('message', sprintf(
            '%d pending %s %s.',
            $processedCount,
            \Illuminate\Support\Str::plural('item', $processedCount),
            $actionLabel
        ));
        session()->flash('message-title', 'Child approvals updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.approvals');
    }

    public function approveTopic(Request $request, User $child, ForumTopic $forumTopic): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumTopic->user_id === (string) $child->id && ! $forumTopic->is_approved, 404);

        $this->approvePendingTopic($parent, $forumTopic);

        session()->flash('message', 'Pending thread approved.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function approveTopicFromEmail(Request $request, User $child, ForumTopic $forumTopic): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumTopic->user_id === (string) $child->id, 404);

        if ($forumTopic->is_approved) {
            session()->flash('message', 'That thread has already been approved.');
            session()->flash('message-title', 'Already approved');
            session()->flash('message-type', 'info');

            return redirect()->route('account.children.edit', $child);
        }

        $this->approvePendingTopic($parent, $forumTopic);

        session()->flash('message', 'Pending thread approved from email.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function rejectTopic(Request $request, User $child, ForumTopic $forumTopic): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumTopic->user_id === (string) $child->id && ! $forumTopic->is_approved, 404);

        $this->rejectPendingTopic($forumTopic);

        session()->flash('message', 'Pending thread discarded.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function approvePost(Request $request, User $child, ForumPost $forumPost): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumPost->user_id === (string) $child->id && ! $forumPost->is_approved, 404);

        $this->approvePendingPost($parent, $forumPost);

        session()->flash('message', 'Pending reply approved.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function approvePostFromEmail(Request $request, User $child, ForumPost $forumPost): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumPost->user_id === (string) $child->id, 404);

        if ($forumPost->is_approved) {
            session()->flash('message', 'That reply has already been approved.');
            session()->flash('message-title', 'Already approved');
            session()->flash('message-type', 'info');

            return redirect()->route('account.children.edit', $child);
        }

        $this->approvePendingPost($parent, $forumPost);

        session()->flash('message', 'Pending reply approved from email.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function rejectPost(Request $request, User $child, ForumPost $forumPost): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumPost->user_id === (string) $child->id && ! $forumPost->is_approved, 404);

        $this->rejectPendingPost($forumPost);

        session()->flash('message', 'Pending reply discarded.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    private function authorizeChildManagement(?User $parent, User $child): void
    {
        abort_unless(
            $parent && $parent->canManageChildAccount($child) && ! $child->isAnonymized(),
            403
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function avatarValidationRules(User $parent): array
    {
        return [
            'avatar_mode' => ['nullable', 'string', Rule::in(User::avatarModes())],
            'avatar_letters' => ['nullable', 'string', 'max:3'],
            'avatar_icon_class' => ['nullable', 'string', Rule::in(User::avatarIconOptions())],
            'avatar_background_color' => ['nullable', 'string', 'max:7'],
            'avatar_media_name' => [
                'nullable',
                'string',
                'exists:media,name',
                function (string $attribute, mixed $value, \Closure $fail) use ($parent): void {
                    $mediaName = trim((string) $value);
                    if ($mediaName === '') {
                        return;
                    }

                    $media = Media::query()->find($mediaName);
                    if (! $media) {
                        return;
                    }

                    if (! $parent->isAdmin() && (string) $media->user_id !== (string) $parent->id) {
                        $fail('You can only use media that you uploaded for this avatar.');
                    }
                },
            ],
            'avatar_zoom' => ['nullable', 'integer', 'min:100', 'max:250'],
            'avatar_offset_x' => ['nullable', 'integer', 'min:-50', 'max:50'],
            'avatar_offset_y' => ['nullable', 'integer', 'min:-50', 'max:50'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    private function fillAvatarSettings(User $child, array $validated): void
    {
        if (User::hasDatabaseColumn('avatar_mode')) {
            $child->avatar_mode = User::normalizeAvatarMode((string) ($validated['avatar_mode'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_letters')) {
            $child->avatar_letters = User::normalizeAvatarLetters((string) ($validated['avatar_letters'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_icon_class')) {
            $child->avatar_icon_class = User::normalizeAvatarIconClass((string) ($validated['avatar_icon_class'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_background_color')) {
            $child->avatar_background_color = User::normalizeAvatarBackgroundColor((string) ($validated['avatar_background_color'] ?? ''));
        }
        if (User::hasDatabaseColumn('avatar_media_name')) {
            $child->avatar_media_name = trim((string) ($validated['avatar_media_name'] ?? '')) ?: null;
        }
        if (User::hasDatabaseColumn('avatar_zoom')) {
            $child->avatar_zoom = (int) ($validated['avatar_zoom'] ?? 100);
        }
        if (User::hasDatabaseColumn('avatar_offset_x')) {
            $child->avatar_offset_x = (int) ($validated['avatar_offset_x'] ?? 0);
        }
        if (User::hasDatabaseColumn('avatar_offset_y')) {
            $child->avatar_offset_y = (int) ($validated['avatar_offset_y'] ?? 0);
        }

        if (
            User::hasDatabaseColumn('avatar_mode')
            && User::hasDatabaseColumn('avatar_media_name')
            && $child->avatar_mode === User::AVATAR_MODE_MEDIA
            && $child->avatar_media_name === null
        ) {
            $child->avatar_mode = $child->avatar_icon_class ? User::AVATAR_MODE_ICON : User::AVATAR_MODE_LETTERS;
        }

        if (User::hasDatabaseColumn('avatar_media_name') && $child->avatar_media_name === null) {
            if (User::hasDatabaseColumn('avatar_zoom')) {
                $child->avatar_zoom = 100;
            }
            if (User::hasDatabaseColumn('avatar_offset_x')) {
                $child->avatar_offset_x = 0;
            }
            if (User::hasDatabaseColumn('avatar_offset_y')) {
                $child->avatar_offset_y = 0;
            }
        }
    }

    private function fillDiscussionPermissions(User $child, Request $request, bool $creating = false): void
    {
        $child->child_can_create_forum_topics = $request->boolean('child_can_create_forum_topics', $creating);
        $child->child_can_reply_in_forum = $request->boolean('child_can_reply_in_forum', $creating);

        $topicRequiresApproval = $request->boolean('child_forum_topic_requires_approval');
        $replyRequiresApproval = $request->boolean('child_forum_reply_requires_approval');

        $child->child_forum_topic_requires_approval = $child->child_can_create_forum_topics && $topicRequiresApproval;
        $child->child_parent_notified_on_forum_topics = $child->child_can_create_forum_topics
            && ! $topicRequiresApproval
            && $request->boolean('child_parent_notified_on_forum_topics');

        $child->child_forum_reply_requires_approval = $child->child_can_reply_in_forum && $replyRequiresApproval;
        $child->child_parent_notified_on_forum_replies = $child->child_can_reply_in_forum
            && ! $replyRequiresApproval
            && $request->boolean('child_parent_notified_on_forum_replies');
    }

    private function approvePendingTopic(User $parent, ForumTopic $forumTopic): void
    {
        $firstPost = ForumPost::query()
            ->where('forum_topic_id', (string) $forumTopic->id)
            ->where('is_topic_starter', true)
            ->firstOrFail();

        $forumTopic->is_approved = true;
        $forumTopic->approved_by_user_id = (string) $parent->id;
        $forumTopic->last_post_at = $firstPost->created_at;
        $forumTopic->last_post_user_id = (string) $firstPost->user_id;
        $forumTopic->save();

        $firstPost->is_approved = true;
        $firstPost->approved_by_user_id = (string) $parent->id;
        $firstPost->save();
    }

    private function approvePendingPost(User $parent, ForumPost $forumPost): void
    {
        $forumPost->is_approved = true;
        $forumPost->approved_by_user_id = (string) $parent->id;
        $forumPost->save();

        $topic = $forumPost->topic;
        if ($topic && $topic->is_approved) {
            $approvedPostCreatedAt = $forumPost->created_at;

            if (
                $topic->last_post_at === null
                || ($approvedPostCreatedAt !== null && $approvedPostCreatedAt->greaterThanOrEqualTo($topic->last_post_at))
            ) {
                $topic->last_post_at = $approvedPostCreatedAt;
                $topic->last_post_user_id = $forumPost->user_id;
                $topic->save();
            }
        }
    }

    private function rejectPendingTopic(ForumTopic $forumTopic): void
    {
        $forumTopic->delete();
    }

    private function rejectPendingPost(ForumPost $forumPost): void
    {
        $forumPost->delete();
    }

    private function pendingForumData(User $child): array
    {
        $pendingTopics = ForumTopic::query()
            ->with('category')
            ->where('user_id', (string) $child->id)
            ->where('is_approved', false)
            ->orderBy('created_at')
            ->get()
            ->map(function (ForumTopic $topic): array {
                $firstPost = ForumPost::query()
                    ->where('forum_topic_id', (string) $topic->id)
                    ->where('is_topic_starter', true)
                    ->firstOrFail();

                return [
                    'topic' => $topic,
                    'preview' => ForumContent::emailPreviewText((string) $firstPost->body),
                ];
            });

        $pendingReplies = ForumPost::query()
            ->with(['topic.category'])
            ->where('user_id', (string) $child->id)
            ->where('is_approved', false)
            ->whereHas('topic', fn ($query) => $query->where('is_approved', true))
            ->orderBy('created_at')
            ->get()
            ->map(fn (ForumPost $post): array => [
                'post' => $post,
                'preview' => ForumContent::emailPreviewText((string) $post->body),
            ]);

        return [
            'pendingTopics' => $pendingTopics,
            'pendingReplies' => $pendingReplies,
        ];
    }

    private function pendingForumDataForParent(User $parent): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<string, User> $children */
        $children = $parent->children()
            ->whereNull('anonymized_at')
            ->orderBy('username')
            ->get()
            ->keyBy('id');

        $childIds = $children->keys()->all();

        /** @var \Illuminate\Support\Collection<int, array{kind: string, selection_key: string, child: User|null, created_at: \Carbon\Carbon|null, category_name: string, title: string, preview: string}> $pendingTopics */
        $pendingTopics = ForumTopic::query()
            ->with(['category', 'user'])
            ->whereIn('user_id', $childIds)
            ->where('is_approved', false)
            ->orderBy('created_at')
            ->get()
            ->map(function (ForumTopic $topic): array {
                $firstPost = ForumPost::query()
                    ->where('forum_topic_id', (string) $topic->id)
                    ->where('is_topic_starter', true)
                    ->firstOrFail();

                return [
                    'kind' => 'thread',
                    'selection_key' => 'topic:'.$topic->id,
                    'child' => $topic->user instanceof User ? $topic->user : null,
                    'created_at' => $this->nullableForumDateTime($topic->created_at),
                    'category_name' => (string) $topic->category->name,
                    'title' => $topic->plainTitle(),
                    'preview' => ForumContent::emailPreviewText((string) $firstPost->body),
                ];
            });

        /** @var \Illuminate\Support\Collection<int, array{kind: string, selection_key: string, child: User|null, created_at: \Carbon\Carbon|null, category_name: string, title: string, preview: string}> $pendingReplies */
        $pendingReplies = ForumPost::query()
            ->with(['topic.category', 'user'])
            ->whereIn('user_id', $childIds)
            ->where('is_approved', false)
            ->whereHas('topic', fn ($query) => $query->where('is_approved', true))
            ->orderBy('created_at')
            ->get()
            ->map(fn (ForumPost $post): array => [
                'kind' => 'reply',
                'selection_key' => 'post:'.$post->id,
                'child' => $post->user instanceof User ? $post->user : null,
                'created_at' => $this->nullableForumDateTime($post->created_at),
                'category_name' => (string) $post->topic->category->name,
                'title' => $post->topic->plainTitle() ?: 'Discussion thread',
                'preview' => ForumContent::emailPreviewText((string) $post->body),
            ]);

        $pendingItems = collect(array_merge($pendingTopics->all(), $pendingReplies->all()))
            ->sortBy([
                ['created_at', 'asc'],
                ['kind', 'asc'],
            ])
            ->values();

        $groupedItems = [];
        foreach ($children as $child) {
            $items = $pendingItems
                ->filter(fn (array $item) => $item['child'] instanceof User && (string) $item['child']->id === (string) $child->id)
                ->values();

            if ($items->isEmpty()) {
                continue;
            }

            $groupedItems[] = [
                'child' => $child,
                'items' => $items->all(),
                'count' => $items->count(),
            ];
        }

        return [
            'childApprovalGroups' => $groupedItems,
            'pendingApprovalCount' => $pendingItems->count(),
        ];
    }

    /**
     * @param  array<int, string>  $selectedItems
     * @return array{0: array<int, string>, 1: array<int, string>}
     */
    private function parseSelectedApprovalItems(array $selectedItems): array
    {
        $topicIds = [];
        $postIds = [];

        foreach ($selectedItems as $selectedItem) {
            if (preg_match('/^topic:(.+)$/', $selectedItem, $topicMatches) === 1) {
                $topicId = trim((string) $topicMatches[1]);
                if ($topicId !== '') {
                    $topicIds[] = $topicId;
                }
            }

            if (preg_match('/^post:(.+)$/', $selectedItem, $postMatches) === 1) {
                $postId = trim((string) $postMatches[1]);
                if ($postId !== '') {
                    $postIds[] = $postId;
                }
            }
        }

        return [
            array_values(array_unique($topicIds)),
            array_values(array_unique($postIds)),
        ];
    }

    private function nullableForumDateTime(mixed $value): ?\Carbon\Carbon
    {
        return $value instanceof \Carbon\Carbon ? $value : null;
    }
}
