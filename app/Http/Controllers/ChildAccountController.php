<?php

namespace App\Http\Controllers;

use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use App\Rules\UsernameRule;
use App\Support\ForumContent;
use App\Support\UserAnonymizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
            ]),
            'isNew' => true,
            'pendingTopics' => collect(),
            'pendingReplies' => collect(),
        ]);
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
        ]);

        $child = new User();
        $child->parent_user_id = (string) $parent->id;
        $child->username = User::normalizeUsername((string) $validated['username']);
        $child->password = (string) $validated['password'];
        $child->email = null;
        $child->email_verified_at = null;
        $child->child_can_create_forum_topics = $request->boolean('child_can_create_forum_topics', true);
        $child->child_can_reply_in_forum = $request->boolean('child_can_reply_in_forum', true);
        $child->child_forum_topic_requires_approval = $request->boolean('child_forum_topic_requires_approval');
        $child->child_forum_reply_requires_approval = $request->boolean('child_forum_reply_requires_approval');
        $child->child_parent_notified_on_forum_topics = $request->boolean('child_parent_notified_on_forum_topics');
        $child->child_parent_notified_on_forum_replies = $request->boolean('child_parent_notified_on_forum_replies');
        $child->save();

        session()->flash('message', 'Child account created.');
        session()->flash('message-title', 'Child account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
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
        ]);

        $child->username = User::normalizeUsername((string) $validated['username']);
        $child->child_can_create_forum_topics = $request->boolean('child_can_create_forum_topics');
        $child->child_can_reply_in_forum = $request->boolean('child_can_reply_in_forum');
        $child->child_forum_topic_requires_approval = $request->boolean('child_forum_topic_requires_approval');
        $child->child_forum_reply_requires_approval = $request->boolean('child_forum_reply_requires_approval');
        $child->child_parent_notified_on_forum_topics = $request->boolean('child_parent_notified_on_forum_topics');
        $child->child_parent_notified_on_forum_replies = $request->boolean('child_parent_notified_on_forum_replies');

        if (trim((string) ($validated['password'] ?? '')) !== '') {
            $child->password = (string) $validated['password'];
        }

        $child->save();

        session()->flash('message', 'Child account updated.');
        session()->flash('message-title', 'Child account saved');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
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

    public function approveTopic(Request $request, User $child, ForumTopic $forumTopic): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumTopic->user_id === (string) $child->id && ! $forumTopic->is_approved, 404);

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

        session()->flash('message', 'Pending thread approved.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function rejectTopic(Request $request, User $child, ForumTopic $forumTopic): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumTopic->user_id === (string) $child->id && ! $forumTopic->is_approved, 404);

        $forumTopic->delete();

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

        session()->flash('message', 'Pending reply approved.');
        session()->flash('message-title', 'Discussion updated');
        session()->flash('message-type', 'success');

        return redirect()->route('account.children.edit', $child);
    }

    public function rejectPost(Request $request, User $child, ForumPost $forumPost): RedirectResponse
    {
        $parent = $request->user();
        $this->authorizeChildManagement($parent, $child);
        abort_unless((string) $forumPost->user_id === (string) $child->id && ! $forumPost->is_approved, 404);

        $forumPost->delete();

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
                    'preview' => ForumContent::plainText((string) $firstPost->body),
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
                'preview' => ForumContent::plainText((string) $post->body),
            ]);

        return [
            'pendingTopics' => $pendingTopics,
            'pendingReplies' => $pendingReplies,
        ];
    }
}
