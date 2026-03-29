<?php

namespace App\Services\Classroom;

use App\Models\ClassSession;
use App\Models\ForumCategory;
use App\Models\UserGroup;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ClassroomAdminSyncService
{
    public function syncDerivedLinks(ClassSession $classSession, ?string $originalSlug = null): void
    {
        $currentSlug = UserGroup::normalizeSlug((string) $classSession->slug);
        if ($currentSlug === '') {
            return;
        }

        $previousSlug = UserGroup::normalizeSlug((string) $originalSlug);
        if ($previousSlug !== '' && $previousSlug !== $currentSlug) {
            $this->renameGroups($previousSlug, $currentSlug);
            $this->renameForumAccessGroups($previousSlug, $currentSlug);
        }

        if ($classSession->access_group_slug !== $currentSlug) {
            $classSession->forceFill([
                'access_group_slug' => $currentSlug,
            ])->saveQuietly();
        }

        $classSession->loadMissing('forumCategory', 'workshop');

        if ($classSession->forum_category_id === null && $classSession->workshop?->classroom_forum_category_id) {
            $classSession->forceFill([
                'forum_category_id' => $classSession->workshop->classroom_forum_category_id,
            ])->saveQuietly();
            $classSession->loadMissing('forumCategory');
        }

        if (($classSession->broadcast_sessions_json === null || $classSession->broadcast_sessions_json === []) && is_array($classSession->workshop?->classroom_sessions_json ?? null)) {
            $classSession->forceFill([
                'broadcast_sessions_json' => $classSession->workshop->classroom_sessions_json,
            ])->saveQuietly();
        }

        if ($classSession->forumCategory instanceof ForumCategory) {
            $classSession->forumCategory->forceFill([
                'read_group_slug' => $currentSlug,
                'write_group_slug' => $currentSlug,
            ])->save();
        }

        if ($classSession->workshop) {
            $classSession->workshop->forceFill([
                'ticket_group_slug' => $currentSlug,
                'classroom_forum_category_id' => $classSession->forum_category_id,
                'classroom_sessions_json' => $classSession->broadcast_sessions_json ?? [],
            ])->saveQuietly();
        }
    }

    public function ensureForumCategory(ClassSession $classSession, ?string $name, bool $createIfMissing): ?ForumCategory
    {
        $classSession->loadMissing('forumCategory');
        $existing = $classSession->forumCategory;
        $normalizedGroup = UserGroup::normalizeSlug((string) $classSession->slug);

        if ($existing instanceof ForumCategory) {
            $forumName = trim((string) ($name ?? ''));
            if ($forumName !== '' && $existing->name !== $forumName) {
                $existing->name = $forumName;
            }

            $existing->read_group_slug = $normalizedGroup;
            $existing->write_group_slug = $normalizedGroup;
            $existing->save();

            $classSession->forceFill([
                'forum_category_id' => $existing->id,
            ])->saveQuietly();

            return $existing;
        }

        if (! $createIfMissing) {
            return null;
        }

        $forumName = trim((string) ($name ?? ''));
        if ($forumName === '') {
            $forumName = $classSession->title.' Forum';
        }

        $forumCategory = new ForumCategory();
        $forumCategory->name = $forumName;
        $forumCategory->slug = $this->nextAvailableForumSlug($forumName);
        $forumCategory->read_group_slug = $normalizedGroup;
        $forumCategory->write_group_slug = $normalizedGroup;
        $forumCategory->save();

        $classSession->forceFill([
            'forum_category_id' => $forumCategory->id,
        ])->saveQuietly();

        return $forumCategory;
    }

    private function renameGroups(string $previousSlug, string $currentSlug): void
    {
        DB::transaction(function () use ($previousSlug, $currentSlug): void {
            $groups = UserGroup::query()
                ->where('slug', $previousSlug)
                ->orderBy('id')
                ->get();

            foreach ($groups as $group) {
                $existing = UserGroup::query()
                    ->where('user_id', $group->user_id)
                    ->where('slug', $currentSlug)
                    ->exists();

                if ($existing) {
                    $group->delete();
                    continue;
                }

                $group->slug = $currentSlug;
                $group->save();
            }
        });
    }

    private function renameForumAccessGroups(string $previousSlug, string $currentSlug): void
    {
        ForumCategory::query()
            ->where(function ($builder) use ($previousSlug): void {
                $builder->where('read_group_slug', $previousSlug)
                    ->orWhere('write_group_slug', $previousSlug);
            })
            ->get()
            ->each(function (ForumCategory $forumCategory) use ($currentSlug): void {
                $forumCategory->read_group_slug = $currentSlug;
                $forumCategory->write_group_slug = $currentSlug;
                $forumCategory->save();
            });
    }

    private function nextAvailableForumSlug(string $name): string
    {
        $base = ForumCategory::normalizeSlug($name);
        $base = $base !== '' ? $base : 'forum-category';
        $slug = $base;
        $suffix = 2;

        while (ForumCategory::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
