<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\ChildForumActivityNotification;
use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\Media;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ChildAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_parent_can_create_child_account_and_child_can_log_in_with_password(): void
    {
        $parent = User::factory()->create();

        $response = $this->actingAs($parent)->post(route('account.children.store'), [
            'username' => 'kid-forum',
            'password' => 'secret1234',
            'password_confirmation' => 'secret1234',
            'child_can_create_forum_topics' => '1',
            'child_can_reply_in_forum' => '1',
        ]);

        $child = User::query()->where('username', 'kid-forum')->firstOrFail();

        $response->assertRedirect(route('account.show'));
        $this->assertSame((string) $parent->id, (string) $child->parent_user_id);
        $this->assertTrue($child->isChildAccount());

        auth()->logout();

        $loginResponse = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'kid-forum',
            'password' => 'secret1234',
            'remember_email' => '0',
        ]);

        $loginResponse->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($child);
    }

    public function test_child_password_login_persists_across_follow_up_page_requests(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-persist',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);

        $loginResponse = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'kid-persist',
            'password' => 'secret1234',
            'remember_email' => '0',
        ]);

        $loginResponse->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($child);

        $this->get(route('index'))
            ->assertOk()
            ->assertSee('Log out')
            ->assertDontSee('Log in');
        $this->assertAuthenticatedAs($child);

        $this->get(route('account.show'))
            ->assertOk()
            ->assertSee('Account Settings');
        $this->assertAuthenticatedAs($child);

        $this->get(route('forum.index'))->assertOk();
        $this->assertAuthenticatedAs($child);
    }

    public function test_child_account_continue_shows_password_prompt(): void
    {
        $parent = User::factory()->create();
        User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-login',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);

        $response = $this->followingRedirects()
            ->withSession([
                'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
            ])->post(route('login.store'), [
                'login' => 'kid-login',
                'remember_email' => '0',
            ]);

        $response->assertOk();
        $response->assertSee('Enter your password');
        $response->assertSee('Enter the password for this account to continue');
        $response->assertSee('name="password"', false);
        $response->assertSee(route('login'), false);
        $response->assertDontSee('Email or Username');
        $response->assertDontSee('id="login_identifier"', false);
        $this->assertGuest();
    }

    public function test_verified_full_user_can_log_in_with_password(): void
    {
        $user = User::factory()->create([
            'username' => 'member-pass',
            'password' => 'secret1234',
            'email_verified_at' => now(),
        ]);

        $response = $this->withSession([
            'altcha_trusted_until' => Carbon::now()->addMinutes(60)->getTimestamp(),
        ])->post(route('login.store'), [
            'login' => 'member-pass',
            'password' => 'secret1234',
            'remember_email' => '0',
        ]);

        $response->assertRedirect(route('index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_child_account_is_blocked_from_full_account_routes_and_forum_permissions_can_be_disabled(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
            'child_can_create_forum_topics' => false,
            'child_can_reply_in_forum' => false,
        ]);

        $category = $this->createCategory('General', 'general');
        [$topic] = $this->createTopicWithFirstPost($category, $parent, 'Welcome');

        $this->actingAs($child)
            ->get(route('account.ticket.index'))
            ->assertRedirect(route('account.show'));

        $this->actingAs($child)
            ->post(route('forum.topic.store', $category->slug), [
                'title' => 'Blocked thread',
                'body' => '<p>Not allowed.</p>',
            ])
            ->assertForbidden();

        $this->actingAs($child)
            ->post(route('forum.post.store', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
            ]), [
                'body' => '<p>Blocked reply.</p>',
            ])
            ->assertForbidden();
    }

    public function test_parent_can_manage_child_avatar_styles_and_avatar_permissions(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
        ]);
        $avatar = Media::factory()->create([
            'user_id' => (string) $parent->id,
        ]);

        $this->actingAs($parent)
            ->put(route('account.children.update', $child), [
                'username' => (string) $child->username,
                'child_can_create_forum_topics' => '1',
                'child_can_reply_in_forum' => '1',
                'child_can_select_avatar_media' => '1',
                'child_can_use_avatar_camera' => '1',
                'avatar_mode' => User::AVATAR_MODE_MEDIA,
                'avatar_media_name' => (string) $avatar->name,
                'avatar_zoom' => '145',
                'avatar_offset_x' => '12',
                'avatar_offset_y' => '-8',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $this->assertSame(User::AVATAR_MODE_MEDIA, $child->avatar_mode);
        $this->assertSame((string) $avatar->name, (string) $child->avatar_media_name);
        $this->assertSame(145, (int) $child->avatar_zoom);
        $this->assertSame(12, (int) $child->avatar_offset_x);
        $this->assertSame(-8, (int) $child->avatar_offset_y);
        $this->assertTrue($child->child_can_select_avatar_media);
        $this->assertTrue($child->child_can_use_avatar_camera);

        $this->actingAs($parent)
            ->put(route('account.children.update', $child), [
                'username' => (string) $child->username,
                'child_can_create_forum_topics' => '1',
                'child_can_reply_in_forum' => '1',
                'child_can_select_avatar_media' => '0',
                'child_can_use_avatar_camera' => '1',
                'avatar_mode' => User::AVATAR_MODE_ICON,
                'avatar_icon_class' => 'fa-solid fa-robot',
                'avatar_background_color' => '#0EA5E9',
                'avatar_media_name' => '',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $this->assertSame(User::AVATAR_MODE_ICON, $child->avatar_mode);
        $this->assertSame('fa-solid fa-robot', $child->avatar_icon_class);
        $this->assertSame('#0EA5E9', $child->avatar_background_color);
        $this->assertNull($child->avatar_media_name);
        $this->assertSame(100, (int) $child->avatar_zoom);
        $this->assertSame(0, (int) $child->avatar_offset_x);
        $this->assertSame(0, (int) $child->avatar_offset_y);
        $this->assertFalse($child->child_can_select_avatar_media);
        $this->assertFalse($child->child_can_use_avatar_camera);

        $this->actingAs($parent)
            ->put(route('account.children.update', $child), [
                'username' => (string) $child->username,
                'child_can_create_forum_topics' => '1',
                'child_can_reply_in_forum' => '1',
                'child_can_select_avatar_media' => '0',
                'child_can_use_avatar_camera' => '0',
                'avatar_mode' => User::AVATAR_MODE_LETTERS,
                'avatar_letters' => 'sm2',
                'avatar_background_color' => '16a34a',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $this->assertSame(User::AVATAR_MODE_LETTERS, $child->avatar_mode);
        $this->assertSame('SM2', $child->avatar_letters);
        $this->assertSame('#16A34A', $child->avatar_background_color);
    }

    public function test_child_forum_approval_and_email_notification_settings_are_mutually_exclusive_when_saved(): void
    {
        $parent = User::factory()->create();

        $this->actingAs($parent)
            ->post(route('account.children.store'), [
                'username' => 'kid-moderation',
                'password' => 'secret1234',
                'password_confirmation' => 'secret1234',
                'child_can_create_forum_topics' => '1',
                'child_can_reply_in_forum' => '1',
                'child_forum_topic_requires_approval' => '1',
                'child_parent_notified_on_forum_topics' => '1',
                'child_forum_reply_requires_approval' => '1',
                'child_parent_notified_on_forum_replies' => '1',
            ])
            ->assertRedirect(route('account.show'));

        $child = User::query()->where('username', 'kid-moderation')->firstOrFail();
        $this->assertTrue($child->child_forum_topic_requires_approval);
        $this->assertFalse($child->child_parent_notified_on_forum_topics);
        $this->assertTrue($child->child_forum_reply_requires_approval);
        $this->assertFalse($child->child_parent_notified_on_forum_replies);

        $this->actingAs($parent)
            ->put(route('account.children.update', $child), [
                'username' => 'kid-moderation',
                'child_can_create_forum_topics' => '1',
                'child_can_reply_in_forum' => '1',
                'child_forum_topic_requires_approval' => '0',
                'child_parent_notified_on_forum_topics' => '1',
                'child_forum_reply_requires_approval' => '1',
                'child_parent_notified_on_forum_replies' => '1',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $this->assertFalse($child->child_forum_topic_requires_approval);
        $this->assertTrue($child->child_parent_notified_on_forum_topics);
        $this->assertTrue($child->child_forum_reply_requires_approval);
        $this->assertFalse($child->child_parent_notified_on_forum_replies);
    }

    public function test_child_cannot_change_avatar_when_parent_disables_avatar_editing(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'child_can_select_avatar_media' => false,
            'child_can_use_avatar_camera' => false,
            'avatar_mode' => User::AVATAR_MODE_LETTERS,
            'avatar_letters' => 'KID',
            'avatar_background_color' => '#2563EB',
        ]);
        $childMedia = Media::factory()->create([
            'user_id' => (string) $child->id,
        ]);

        $this->actingAs($child)
            ->get(route('account.show'))
            ->assertOk()
            ->assertDontSee('x-on:click.prevent="openAvatarPicker()"', false)
            ->assertDontSee('Remove Image')
            ->assertDontSee('Image avatars are disabled for this child account.');

        $this->actingAs($child)
            ->from(route('account.show'))
            ->post(route('account.update'), [
                'username' => (string) $child->username,
                'avatar_mode' => User::AVATAR_MODE_MEDIA,
                'avatar_media_name' => (string) $childMedia->name,
                'avatar_letters' => 'NEW',
                'avatar_background_color' => '#16A34A',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $this->assertNull($child->avatar_media_name);
        $this->assertSame(User::AVATAR_MODE_LETTERS, $child->resolvedAvatarMode());
        $this->assertSame('KID', $child->avatar_letters);
        $this->assertSame('#2563EB', $child->avatar_background_color);
    }

    public function test_child_reply_can_require_parent_approval_and_sends_notification_email(): void
    {
        Queue::fake();

        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
            'child_forum_reply_requires_approval' => true,
            'child_parent_notified_on_forum_replies' => true,
        ]);

        $category = $this->createCategory('Ideas', 'ideas');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Shared topic');

        $response = $this->actingAs($child)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>Pending reply body.</p>',
        ]);

        $response->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'sort' => 'oldest',
        ]));

        $pendingReply = ForumPost::query()
            ->where('user_id', (string) $child->id)
            ->where('forum_topic_id', (string) $topic->id)
            ->where('is_approved', false)
            ->firstOrFail();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($parent): bool {
            return $job->to === $parent->email
                && $job->mailable instanceof ChildForumActivityNotification;
        });

        $this->actingAs($parent)
            ->post(route('account.children.post.approve', [
                'child' => $child,
                'forumPost' => $pendingReply,
            ]))
            ->assertRedirect(route('account.children.edit', $child));

        $pendingReply->refresh();
        $topic->refresh();

        $this->assertTrue((bool) $pendingReply->is_approved);
        $this->assertSame((string) $parent->id, (string) $pendingReply->approved_by_user_id);
        $this->assertSame((string) $child->id, (string) $topic->last_post_user_id);

        $this->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))->assertSee('Pending reply body.');
    }

    public function test_parent_can_approve_pending_reply_from_email_link(): void
    {
        Queue::fake();

        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
            'child_forum_reply_requires_approval' => true,
            'child_parent_notified_on_forum_replies' => true,
        ]);

        $category = $this->createCategory('Ideas', 'ideas');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Shared topic');

        $this->actingAs($child)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>Approve from email.</p>',
        ])->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'sort' => 'oldest',
        ]));

        $pendingReply = ForumPost::query()
            ->where('user_id', (string) $child->id)
            ->where('forum_topic_id', (string) $topic->id)
            ->where('is_approved', false)
            ->firstOrFail();

        $approveUrl = null;

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($parent, &$approveUrl): bool {
            if ($job->to !== $parent->email || ! $job->mailable instanceof ChildForumActivityNotification) {
                return false;
            }

            $approveUrl = $job->mailable->approveUrl;

            return $approveUrl !== null
                && str_contains($job->mailable->render(), 'Approve Reply');
        });

        $this->assertNotNull($approveUrl);

        $this->actingAs($parent)
            ->get($approveUrl)
            ->assertRedirect(route('account.children.edit', $child))
            ->assertSessionHas('message', 'Pending reply approved from email.');

        $pendingReply->refresh();
        $topic->refresh();

        $this->assertTrue((bool) $pendingReply->is_approved);
        $this->assertSame((string) $parent->id, (string) $pendingReply->approved_by_user_id);
        $this->assertSame((string) $child->id, (string) $topic->last_post_user_id);
    }

    public function test_pending_child_forum_submissions_email_parent_and_show_parent_review_indicators(): void
    {
        Queue::fake();

        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
            'child_forum_topic_requires_approval' => true,
            'child_forum_reply_requires_approval' => true,
            'child_parent_notified_on_forum_topics' => false,
            'child_parent_notified_on_forum_replies' => false,
        ]);

        $category = $this->createCategory('Ideas', 'ideas');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Shared topic');

        $this->actingAs($child)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Pending child thread',
            'body' => '<p>This is the test that I am testing with:</p><ul><li>Point 1</li><li>Point 2</li><li>Point 3</li></ul><p>And this is the last line</p>',
        ])->assertRedirect(route('forum.category.show', $category->slug));

        $this->actingAs($child)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>Pending child reply.</p>',
        ])->assertRedirect(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'sort' => 'oldest',
        ]));

        Queue::assertPushed(SendEmail::class, 2);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($parent): bool {
            if (
                $job->to !== $parent->email
                || ! $job->mailable instanceof ChildForumActivityNotification
                || $job->mailable->activityLabel !== 'thread'
            ) {
                return false;
            }

            return $job->mailable->preview === "This is the test that I am testing with:\n- Point 1\n- Point 2\n- Point 3\nAnd this is the last line"
                && $job->mailable->assertSeeInHtml('STEMMechanics Logo') === $job->mailable
                && $job->mailable->assertSeeInHtml('border-left:4px solid #9ca3af;', false) === $job->mailable
                && $job->mailable->assertSeeInHtml('align="center"', false) === $job->mailable
                && $job->mailable->assertDontSeeInHtml('Review Child Account') === $job->mailable
                && $job->mailable->assertSeeInHtml('- Point 1<br />', false) === $job->mailable
                && $job->mailable->assertSeeInHtml('- Point 2<br />', false) === $job->mailable
                && $job->mailable->assertSeeInHtml('- Point 3', false) === $job->mailable
                && $job->mailable->assertSeeInText("- Point 1\n- Point 2\n- Point 3") === $job->mailable
                && $job->mailable->assertSeeInText("And this is the last line\n\nApprove Thread:") === $job->mailable;
        });

        $this->actingAs($parent)
            ->get(route('account.show'))
            ->assertOk()
            ->assertSeeText('2 child approvals pending')
            ->assertSeeText('Child approvals')
            ->assertSeeText('Pending child approvals')
            ->assertSeeText('2 discussion submissions are waiting for review.');
    }

    public function test_parent_can_view_combined_child_approval_queue_and_bulk_approve_selected_items(): void
    {
        $parent = User::factory()->create();
        $author = User::factory()->create();
        $childAlpha = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'alpha-kid',
            'email' => null,
            'email_verified_at' => null,
        ]);
        $childBeta = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'beta-kid',
            'email' => null,
            'email_verified_at' => null,
        ]);
        $category = $this->createCategory('Ideas', 'ideas');
        [$approvedTopic] = $this->createTopicWithFirstPost($category, $author, 'Shared topic');

        $pendingTopic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $childAlpha->id,
            'title' => 'Pending alpha thread',
            'slug' => ForumTopic::generateUniqueSlug('Pending alpha thread', (string) $category->id),
            'last_post_at' => now(),
            'last_post_user_id' => $childAlpha->id,
            'is_approved' => false,
        ]);
        ForumPost::query()->create([
            'forum_topic_id' => $pendingTopic->id,
            'user_id' => $childAlpha->id,
            'body' => '<p>Alpha line 1</p><ul><li>Alpha line 2</li><li>Alpha line 3</li></ul><p>Alpha line 4</p>',
            'is_topic_starter' => true,
            'is_approved' => false,
        ]);

        $pendingReply = ForumPost::query()->create([
            'forum_topic_id' => $approvedTopic->id,
            'user_id' => $childBeta->id,
            'body' => '<p>Beta reply preview.</p>',
            'is_approved' => false,
        ]);

        $this->actingAs($parent)
            ->get(route('account.children.approvals'))
            ->assertOk()
            ->assertSeeText('Combined Approval Queue')
            ->assertSeeText('alpha-kid')
            ->assertSeeText('beta-kid')
            ->assertSeeText('Pending alpha thread')
            ->assertSeeText('Shared topic')
            ->assertSee('title="Approve item"', false)
            ->assertSee('title="Discard item"', false)
            ->assertSee('Alpha line 1<br />', false)
            ->assertSee('- Alpha line 2<br />', false)
            ->assertSee('- Alpha line 3<br />', false)
            ->assertSee('Alpha line 4', false);

        $this->actingAs($parent)
            ->post(route('account.children.approvals.bulk'), [
                'action' => 'approve',
                'selected_items' => [
                    'topic:'.$pendingTopic->id,
                    'post:'.$pendingReply->id,
                ],
            ])
            ->assertRedirect(route('account.children.approvals'))
            ->assertSessionHas('message', '2 pending items approved.');

        $pendingTopic->refresh();
        $pendingReply->refresh();
        $approvedTopic->refresh();

        $this->assertTrue((bool) $pendingTopic->is_approved);
        $this->assertTrue((bool) $pendingReply->is_approved);
        $this->assertSame((string) $parent->id, (string) $pendingTopic->approved_by_user_id);
        $this->assertSame((string) $parent->id, (string) $pendingReply->approved_by_user_id);
        $this->assertSame((string) $childBeta->id, (string) $approvedTopic->last_post_user_id);
    }

    public function test_parent_can_bulk_discard_selected_pending_items(): void
    {
        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'discard-kid',
            'email' => null,
            'email_verified_at' => null,
        ]);
        $category = $this->createCategory('General', 'general');
        [$approvedTopic] = $this->createTopicWithFirstPost($category, $author, 'Existing topic');

        $pendingTopic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $child->id,
            'title' => 'Discard this thread',
            'slug' => ForumTopic::generateUniqueSlug('Discard this thread', (string) $category->id),
            'last_post_at' => now(),
            'last_post_user_id' => $child->id,
            'is_approved' => false,
        ]);
        ForumPost::query()->create([
            'forum_topic_id' => $pendingTopic->id,
            'user_id' => $child->id,
            'body' => '<p>Discard thread body.</p>',
            'is_topic_starter' => true,
            'is_approved' => false,
        ]);

        $pendingReply = ForumPost::query()->create([
            'forum_topic_id' => $approvedTopic->id,
            'user_id' => $child->id,
            'body' => '<p>Discard reply body.</p>',
            'is_approved' => false,
        ]);

        $this->actingAs($parent)
            ->post(route('account.children.approvals.bulk'), [
                'action' => 'reject',
                'selected_items' => [
                    'topic:'.$pendingTopic->id,
                    'post:'.$pendingReply->id,
                ],
            ])
            ->assertRedirect(route('account.children.approvals'))
            ->assertSessionHas('message', '2 pending items discarded.');

        $this->assertDatabaseMissing('forum_topics', [
            'id' => $pendingTopic->id,
        ]);
        $this->assertDatabaseMissing('forum_posts', [
            'id' => $pendingReply->id,
        ]);
    }

    public function test_child_deletion_anonymizes_account_and_releases_username(): void
    {
        $parent = User::factory()->create();
        $author = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'username' => 'kid-delete',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);

        $category = $this->createCategory('General', 'general');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Existing topic');
        $reply = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $child->id,
            'body' => '<p>Reply stays visible.</p>',
        ]);

        $this->actingAs($parent)
            ->delete(route('account.children.destroy', $child), [
                'delete_discussion_threads' => '0',
            ])
            ->assertRedirect(route('account.show'));

        $child->refresh();
        $reply->refresh();

        $this->assertNotNull($child->anonymized_at);
        $this->assertNotSame('kid-delete', (string) $child->username);
        $this->assertNull($child->email);
        $this->assertNull($child->parent_user_id);
        $this->assertSame('<p>Reply stays visible.</p>', $reply->body);

        $replacement = User::factory()->create([
            'username' => 'kid-delete',
        ]);

        $this->assertSame('kid-delete', $replacement->username);

        $this->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))
            ->assertSee('deleted')
            ->assertSee('fa-user-slash', false)
            ->assertSee('text-gray-500', false);
    }

    public function test_reply_deletion_is_soft_deleted_placeholder_for_author(): void
    {
        $author = User::factory()->create();
        $replyAuthor = User::factory()->create();
        $category = $this->createCategory('General', 'general');
        [$topic] = $this->createTopicWithFirstPost($category, $author, 'Thread title');

        $reply = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $replyAuthor->id,
            'body' => '<p>Temporary reply.</p>',
        ]);

        $this->actingAs($replyAuthor)
            ->delete(route('forum.post.destroy', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
                'forumPost' => $reply,
            ]))
            ->assertRedirect();

        $reply->refresh();

        $this->assertNotNull($reply->deleted_at);
        $this->assertSame('<p><em>deleted</em></p>', $reply->body);

        $this->get(route('forum.topic.show', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]))
            ->assertOk()
            ->assertDontSee(route('forum.post.destroy', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
                'forumPost' => $reply,
            ]), false);
    }

    private function createCategory(string $name, string $slug): ForumCategory
    {
        return ForumCategory::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);
    }

    /**
     * @return array{ForumTopic, ForumPost}
     */
    private function createTopicWithFirstPost(ForumCategory $category, User $author, string $title): array
    {
        $topic = ForumTopic::query()->create([
            'forum_category_id' => $category->id,
            'user_id' => $author->id,
            'last_post_user_id' => $author->id,
            'title' => $title,
            'slug' => ForumTopic::generateUniqueSlug($title, (string) $category->id),
            'last_post_at' => now(),
        ]);

        $post = ForumPost::query()->create([
            'forum_topic_id' => $topic->id,
            'user_id' => $author->id,
            'body' => '<p>Seed post body.</p>',
        ]);

        return [$topic, $post];
    }
}
