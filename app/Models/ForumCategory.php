<?php

namespace App\Models;

use App\Traits\UUID;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class ForumCategory extends Model
{
    use HasFactory, UUID;

    public const LOGGED_IN_GROUP_SLUG = 'user';

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon_class',
        'color_hex',
        'read_group_slug',
        'write_group_slug',
        'sort_order',
    ];

    public function topics(): HasMany
    {
        return $this->hasMany(ForumTopic::class)
            ->where('is_approved', true)
            ->orderByDesc('is_pinned')
            ->orderByDesc('last_post_at');
    }

    public function posts(): HasManyThrough
    {
        return $this->hasManyThrough(ForumPost::class, ForumTopic::class)
            ->where('forum_topics.is_approved', true)
            ->where('forum_posts.is_approved', true);
    }

    public function classSession(): HasOne
    {
        return $this->hasOne(ClassSession::class, 'forum_category_id');
    }

    public function canRead(?User $user): bool
    {
        if ($this->isDivider()) {
            return true;
        }

        if ($user?->isAdmin()) {
            return true;
        }

        $readGroupSlug = UserGroup::normalizeSlug((string) ($this->read_group_slug ?? ''));
        if ($readGroupSlug === '') {
            return true;
        }

        if ($readGroupSlug === self::LOGGED_IN_GROUP_SLUG) {
            return $user !== null;
        }

        return $user?->hasGroup($readGroupSlug) ?? false;
    }

    public function canWrite(?User $user): bool
    {
        if ($this->isDivider()) {
            return false;
        }

        if (! $user) {
            return false;
        }

        if ($user->isAdmin()) {
            return true;
        }

        if (! $this->canRead($user)) {
            return false;
        }

        $writeGroupSlug = UserGroup::normalizeSlug((string) ($this->write_group_slug ?? ''));
        if ($writeGroupSlug === '' || $writeGroupSlug === self::LOGGED_IN_GROUP_SLUG) {
            return true;
        }

        return $user->hasGroup($writeGroupSlug);
    }

    public function readAccessLabel(): string
    {
        if ($this->isDivider()) {
            return '-';
        }

        $readGroupSlug = UserGroup::normalizeSlug((string) ($this->read_group_slug ?? ''));
        if ($readGroupSlug === '') {
            return 'Public';
        }

        if ($readGroupSlug === self::LOGGED_IN_GROUP_SLUG) {
            return 'Logged In Users';
        }

        return $readGroupSlug;
    }

    public function writeAccessLabel(): string
    {
        if ($this->isDivider()) {
            return '-';
        }

        $writeGroupSlug = UserGroup::normalizeSlug((string) ($this->write_group_slug ?? ''));
        if ($writeGroupSlug === '' || $writeGroupSlug === self::LOGGED_IN_GROUP_SLUG) {
            return 'Logged In Users';
        }

        return $writeGroupSlug;
    }

    public function isDivider(): bool
    {
        return static::isDividerSlug($this->slug);
    }

    public static function normalizeSlug(string $value): string
    {
        return trim((string) Str::slug($value));
    }

    public static function isDividerSlug(?string $slug): bool
    {
        $slug = trim((string) $slug);

        return $slug !== '' && preg_match('/^-+$/', $slug) === 1;
    }

    public static function normalizeGroupSlug(?string $value): ?string
    {
        $normalized = UserGroup::normalizeSlug((string) $value);

        return $normalized === '' || $normalized === 'no-group' ? null : $normalized;
    }

    public static function normalizeColorHex(?string $value): ?string
    {
        $normalized = strtoupper(trim((string) $value));
        if ($normalized === '') {
            return null;
        }

        if (! str_starts_with($normalized, '#')) {
            $normalized = '#'.$normalized;
        }

        return preg_match('/^#[0-9A-F]{6}$/', $normalized) === 1 ? $normalized : null;
    }

    public static function normalizeIconClass(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return $normalized !== '' ? $normalized : null;
    }
}
