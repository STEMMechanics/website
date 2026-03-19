<?php

namespace App\Support;

use App\Models\EmailSubscriptions;
use App\Models\ForumPost;
use App\Models\ForumTopic;
use App\Models\User;
use App\Observers\AuditLogObserver;
use Illuminate\Support\Facades\DB;

class UserAnonymizer
{
    public function anonymize(User $user, bool $deleteDiscussionThreads = false, bool $cascadeChildren = true): void
    {
        DB::transaction(function () use ($user, $deleteDiscussionThreads, $cascadeChildren): void {
            if ($cascadeChildren) {
                foreach ($user->children()
                    ->whereNull('anonymized_at')
                    ->get()
                    as $child) {
                    if (! $child instanceof User) {
                        continue;
                    }

                    $this->anonymize($child, $deleteDiscussionThreads, false);
                }
            }

            $email = trim((string) ($user->email ?? ''));
            if ($email !== '') {
                EmailSubscriptions::query()->where('email', $email)->delete();
            }

            $this->deletePendingForumContent($user);

            if ($deleteDiscussionThreads) {
                ForumTopic::query()
                    ->where('user_id', (string) $user->id)
                    ->delete();

                ForumPost::query()
                    ->where('user_id', (string) $user->id)
                    ->whereNull('deleted_at')
                    ->get()
                    ->each(fn (ForumPost $post) => $post->softDeleteToPlaceholder());
            }

            $user->tokens()->delete();
            $user->backupCodes()->delete();
            $user->groups()->delete();
            $user->forumTopicStates()->delete();

            if (! $user->isAnonymized()) {
                (new AuditLogObserver)->deleted($user);
            }

            $user->forceFill([
                'parent_user_id' => null,
                'firstname' => null,
                'surname' => null,
                'company' => null,
                'email' => null,
                'email_verified_at' => null,
                'password' => null,
                'remember_token' => null,
                'phone' => null,
                'shipping_address' => null,
                'shipping_address2' => null,
                'shipping_city' => null,
                'shipping_postcode' => null,
                'shipping_state' => null,
                'shipping_country' => null,
                'billing_address' => null,
                'billing_address2' => null,
                'billing_city' => null,
                'billing_postcode' => null,
                'billing_state' => null,
                'billing_country' => null,
                'avatar_media_name' => null,
                'avatar_zoom' => 100,
                'avatar_offset_x' => 0,
                'avatar_offset_y' => 0,
                'tfa_secret' => null,
                'username' => User::generateUniqueUsername('deleted', (string) $user->id, true),
                'child_can_create_forum_topics' => true,
                'child_can_reply_in_forum' => true,
                'child_forum_topic_requires_approval' => false,
                'child_forum_reply_requires_approval' => false,
                'child_parent_notified_on_forum_topics' => false,
                'child_parent_notified_on_forum_replies' => false,
                'anonymized_at' => now(),
            ])->saveQuietly();
        });
    }

    private function deletePendingForumContent(User $user): void
    {
        ForumTopic::query()
            ->where('user_id', (string) $user->id)
            ->where('is_approved', false)
            ->delete();

        ForumPost::query()
            ->where('user_id', (string) $user->id)
            ->where('is_approved', false)
            ->delete();
    }
}
