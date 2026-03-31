<?php

namespace Tests\Feature;

use App\Models\ForumCategory;
use App\Models\ForumPost;
use App\Models\ForumPostAttachment;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ForumPostAttachmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_threads_and_replies_can_include_attachments_and_download_them(): void
    {
        Storage::fake('local');

        $user = User::factory()->create();
        $category = ForumCategory::query()->create([
            'name' => 'General Discussion',
            'slug' => 'general-discussion',
        ]);

        $topicResponse = $this->actingAs($user)->post(route('forum.topic.store', $category->slug), [
            'title' => 'Attachment thread',
            'body' => '<p>Opening post.</p>',
            'attachments' => [
                UploadedFile::fake()->create('starter-guide.pdf', 24, 'application/pdf'),
            ],
        ]);

        $topicResponse->assertRedirect();
        $topic = ForumTopic::query()->where('title', 'Attachment thread')->firstOrFail();
        $starterPost = ForumPost::query()
            ->where('forum_topic_id', $topic->id)
            ->where('is_topic_starter', true)
            ->firstOrFail();
        $starterAttachment = $starterPost->attachments()->firstOrFail();

        $replyResponse = $this->actingAs($user)->post(route('forum.post.store', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
        ]), [
            'body' => '<p>Reply body.</p>',
            'attachments' => [
                UploadedFile::fake()->create('reply-notes.txt', 8, 'text/plain'),
            ],
        ]);

        $replyResponse->assertRedirect();
        $replyPost = ForumPost::query()
            ->where('forum_topic_id', $topic->id)
            ->where('is_topic_starter', false)
            ->firstOrFail();
        $replyAttachment = $replyPost->attachments()->firstOrFail();

        $editResponse = $this->actingAs($user)
            ->withSession([
                '_old_input' => [
                    'edit_post_id' => $replyPost->id,
                    'modal_mode' => 'edit',
                ],
            ])
            ->get(route('forum.topic.show', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
            ]));

        $this->assertDatabaseCount('forum_post_attachments', 2);
        $this->assertDatabaseHas('forum_post_attachments', [
            'forum_post_id' => $starterPost->id,
            'original_filename' => 'starter-guide.pdf',
        ]);
        $this->assertDatabaseHas('forum_post_attachments', [
            'forum_post_id' => $replyPost->id,
            'original_filename' => 'reply-notes.txt',
        ]);

        Storage::disk('local')->assertExists($starterAttachment->storage_path);
        Storage::disk('local')->assertExists($replyAttachment->storage_path);

        $this->actingAs($user)
            ->get(route('forum.topic.show', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
            ]))
            ->assertOk()
            ->assertSee('starter-guide.pdf')
            ->assertSee('reply-notes.txt');

        $editResponse->assertOk()
            ->assertSee('Attachments')
            ->assertSee('reply-notes.txt');

        $appendResponse = $this->actingAs($user)->put(route('forum.post.update', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'forumPost' => $replyPost->id,
        ]), [
            'body' => '<p>Reply body.</p>',
            'modal_mode' => 'edit',
            'edit_post_id' => $replyPost->id,
            'attachments' => [
                UploadedFile::fake()->create('append-guide.zip', 12, 'application/zip'),
            ],
        ]);

        $appendResponse->assertRedirect();

        $replyPost->refresh();
        $addedAttachment = $replyPost->attachments()
            ->where('original_filename', 'append-guide.zip')
            ->firstOrFail();

        $this->assertDatabaseCount('forum_post_attachments', 3);
        $this->assertDatabaseHas('forum_post_attachments', [
            'forum_post_id' => $replyPost->id,
            'original_filename' => 'append-guide.zip',
        ]);
        Storage::disk('local')->assertExists($addedAttachment->storage_path);

        $updateResponse = $this->actingAs($user)->put(route('forum.post.update', [
            'categorySlug' => $category->slug,
            'topicSlug' => $topic->slug,
            'forumPost' => $replyPost->id,
        ]), [
            'body' => '<p>Reply body.</p>',
            'modal_mode' => 'edit',
            'edit_post_id' => $replyPost->id,
            'removed_attachments' => [
                $replyAttachment->id,
            ],
        ]);

        $updateResponse->assertRedirect();

        $replyPost->refresh();
        $this->assertDatabaseCount('forum_post_attachments', 2);
        $this->assertDatabaseMissing('forum_post_attachments', [
            'id' => $replyAttachment->id,
        ]);
        Storage::disk('local')->assertMissing($replyAttachment->storage_path);
        Storage::disk('local')->assertExists($starterAttachment->storage_path);
        Storage::disk('local')->assertExists($addedAttachment->storage_path);

        $this->actingAs($user)
            ->get(route('forum.topic.show', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
            ]))
            ->assertOk()
            ->assertSee('starter-guide.pdf')
            ->assertDontSee('reply-notes.txt');

        $this->actingAs($user)
            ->get(route('forum.post.attachment.download', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
                'forumPost' => $starterPost->id,
                'attachment' => $starterAttachment->id,
            ]))
            ->assertDownload('starter-guide.pdf');

        $this->actingAs($user)
            ->get(route('forum.post.attachment.download', [
                'categorySlug' => $category->slug,
                'topicSlug' => $topic->slug,
                'forumPost' => $replyPost->id,
                'attachment' => $replyAttachment->id,
            ]))
            ->assertNotFound();
    }
}
