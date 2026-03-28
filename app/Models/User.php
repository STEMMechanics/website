<?php

namespace App\Models;

use App\Jobs\SendEmail;
use App\Mail\UserLoginTFADisabled;
use App\Mail\UserLoginTFAEnabled;
use App\Traits\UUID;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable, UUID;

    /**
     * @var array<string, bool>
     */
    protected static array $databaseColumnCache = [];

    public const AVATAR_MODE_MEDIA = 'media';

    public const AVATAR_MODE_LETTERS = 'letters';

    public const AVATAR_MODE_ICON = 'icon';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'parent_user_id',
        'firstname',
        'surname',
        'company',
        'email',
        'password',
        'avatar_media_name',
        'avatar_mode',
        'avatar_letters',
        'avatar_icon_class',
        'avatar_background_color',
        'avatar_zoom',
        'avatar_offset_x',
        'avatar_offset_y',
        'username',
        'phone',
        'shipping_address',
        'shipping_address2',
        'shipping_city',
        'shipping_postcode',
        'shipping_state',
        'shipping_country',
        'billing_address',
        'billing_address2',
        'billing_city',
        'billing_postcode',
        'billing_state',
        'billing_country',
        'subscribed',
        'agree_tos',
        'child_can_create_forum_topics',
        'child_can_reply_in_forum',
        'child_forum_topic_requires_approval',
        'child_forum_reply_requires_approval',
        'child_parent_notified_on_forum_topics',
        'child_parent_notified_on_forum_replies',
        'child_can_select_avatar_media',
        'child_can_use_avatar_camera',
        'anonymized_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'tfa_secret',
    ];

    protected $attributes = [
        'child_can_create_forum_topics' => true,
        'child_can_reply_in_forum' => true,
        'child_forum_topic_requires_approval' => false,
        'child_forum_reply_requires_approval' => false,
        'child_parent_notified_on_forum_topics' => false,
        'child_parent_notified_on_forum_replies' => false,
        'child_can_select_avatar_media' => true,
        'child_can_use_avatar_camera' => true,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'avatar_zoom' => 'integer',
        'avatar_offset_x' => 'integer',
        'avatar_offset_y' => 'integer',
        'child_can_create_forum_topics' => 'boolean',
        'child_can_reply_in_forum' => 'boolean',
        'child_forum_topic_requires_approval' => 'boolean',
        'child_forum_reply_requires_approval' => 'boolean',
        'child_parent_notified_on_forum_topics' => 'boolean',
        'child_parent_notified_on_forum_replies' => 'boolean',
        'child_can_select_avatar_media' => 'boolean',
        'child_can_use_avatar_camera' => 'boolean',
        'anonymized_at' => 'datetime',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected $appends = [
        'subscribed',
        'email_update_pending',
    ];

    public static function boot()
    {
        parent::boot();

        static::creating(function (self $user): void {
            $isAdmin = (bool) ($user->admin ?? false) || $user->hasGroup('admin');
            $user->username = static::ensureUniqueUsername(
                (string) ($user->username ?? ''),
                (string) ($user->email ?? ''),
                $isAdmin,
                null
            );
        });

        static::saving(function (self $user): void {
            $isAdmin = (bool) ($user->admin ?? false) || $user->hasGroup('admin');
            $user->username = static::ensureUniqueUsername(
                (string) ($user->username ?? ''),
                (string) ($user->email ?? ''),
                $isAdmin,
                $user->exists ? (string) $user->id : null
            );
        });

        static::updating(function ($user) {
            if ($user->isDirty('email')) {
                $originalEmail = trim((string) $user->getOriginal('email'));
                $newEmail = trim((string) ($user->email ?? ''));

                if ($originalEmail !== '' && $newEmail !== '') {
                    EmailSubscriptions::where('email', $originalEmail)->update(['email' => $newEmail]);

                    // remove duplicate email subscriptions, favoring those with confirmed dates
                    $subscriptions = EmailSubscriptions::where('email', $newEmail)->orderBy('created_at', 'asc')->get();
                    $confirmed = EmailSubscriptions::where('email', $newEmail)->whereNotNull('confirmed')->orderBy('confirmed', 'asc')->first();
                    if ($subscriptions->count() > 1) {
                        // if there is a confirmed, then delete all the others
                        if ($confirmed) {
                            $subscriptions->each(function ($subscription) use ($confirmed) {
                                if ($subscription->id !== $confirmed->id) {
                                    $subscription->delete();
                                }
                            });
                        } else {
                            // if there is no confirmed, then delete all but the most recent
                            $subscriptions->each(function ($subscription) use ($subscriptions) {
                                if ($subscription->id !== $subscriptions->last()->id) {
                                    $subscription->delete();
                                }
                            });
                        }
                    }
                } elseif ($originalEmail !== '') {
                    EmailSubscriptions::where('email', $originalEmail)->delete();
                }
            }

            if ($user->isDirty('tfa_secret')) {
                $email = trim((string) ($user->email ?? ''));
                if ($user->tfa_secret === null) {
                    $user->backupCodes()->delete();
                    if ($email !== '') {
                        dispatch(new SendEmail($email, new UserLoginTFADisabled($email)))->onQueue('mail');
                    }
                } elseif ($email !== '') {
                    dispatch(new SendEmail($email, new UserLoginTFAEnabled($email)))->onQueue('mail');
                }
            }
        });

        static::deleting(function ($user) {
            $email = trim((string) ($user->email ?? ''));
            if ($email !== '') {
                EmailSubscriptions::where('email', $email)->delete();
            }
        });

    }

    public static function hasDatabaseColumn(string $column): bool
    {
        $table = (new self())->getTable();
        $cacheKey = $table.'.'.$column;

        if (! array_key_exists($cacheKey, static::$databaseColumnCache)) {
            static::$databaseColumnCache[$cacheKey] = Schema::hasColumn($table, $column);
        }

        return static::$databaseColumnCache[$cacheKey];
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    public static function filterToExistingDatabaseColumns(array $attributes): array
    {
        return array_filter(
            $attributes,
            fn (mixed $value, string $key) => static::hasDatabaseColumn($key),
            ARRAY_FILTER_USE_BOTH
        );
    }

    /**
     * @return array{preset_selection_enabled: bool, media_persistence_enabled: bool, image_framing_enabled: bool}
     */
    public static function avatarPersistenceCapabilities(): array
    {
        return [
            'preset_selection_enabled' => static::hasDatabaseColumn('avatar_mode')
                && static::hasDatabaseColumn('avatar_letters')
                && static::hasDatabaseColumn('avatar_icon_class')
                && static::hasDatabaseColumn('avatar_background_color'),
            'media_persistence_enabled' => static::hasDatabaseColumn('avatar_media_name'),
            'image_framing_enabled' => static::hasDatabaseColumn('avatar_zoom')
                && static::hasDatabaseColumn('avatar_offset_x')
                && static::hasDatabaseColumn('avatar_offset_y'),
        ];
    }

    /**
     * Get the tokens for the user.
     */
    /**
     * @return HasMany<Token, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

    /**
     * Get the calculated name of the user.
     */
    public function getName(): string
    {
        if ($this->isAnonymized()) {
            return 'deleted';
        }

        $name = '';

        if ($this->firstname || $this->surname) {
            $name = implode(' ', [$this->firstname, $this->surname]);
        } elseif ((string) $this->username !== '') {
            $name = (string) $this->username;
        } else {
            $email = trim((string) ($this->email ?? ''));
            if (str_contains($email, '@')) {
                $name = substr($email, 0, strpos($email, '@'));
            } else {
                $name = 'Member';
            }
        }

        return $name;
    }

    public function forumDisplayName(): string
    {
        if ($this->isAnonymized()) {
            return 'deleted';
        }

        $username = trim((string) ($this->username ?? ''));
        if ($username !== '') {
            return $username;
        }

        return $this->getName();
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasMany<Quote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class, 'created_by');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * @return HasMany<StoreOrder, $this>
     */
    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * @return HasMany<MinecraftAccount, $this>
     */
    public function minecraftAccounts(): HasMany
    {
        return $this->hasMany(MinecraftAccount::class);
    }

    /**
     * @return HasMany<ClassEnrolment, $this>
     */
    public function classEnrolments(): HasMany
    {
        return $this->hasMany(ClassEnrolment::class);
    }

    /**
     * @return HasMany<ClassHelpRequest, $this>
     */
    public function classHelpRequests(): HasMany
    {
        return $this->hasMany(ClassHelpRequest::class);
    }

    public function forumTopics(): HasMany
    {
        return $this->hasMany(ForumTopic::class);
    }

    public function forumPosts(): HasMany
    {
        return $this->hasMany(ForumPost::class);
    }

    public function forumTopicStates(): HasMany
    {
        return $this->hasMany(ForumTopicUserState::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
    }

    public function avatarMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'avatar_media_name');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_user_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_user_id')->orderBy('created_at');
    }

    public function avatarImageStyle(): string
    {
        $zoom = max(100, min(250, (int) ($this->avatar_zoom ?? 100))) / 100;
        $offsetX = max(-50, min(50, (int) ($this->avatar_offset_x ?? 0)));
        $offsetY = max(-50, min(50, (int) ($this->avatar_offset_y ?? 0)));

        return sprintf(
            'width:100%%;height:100%%;object-fit:cover;transform:translate(%d%%,%d%%) scale(%.2F);transform-origin:center center;',
            $offsetX,
            $offsetY,
            $zoom
        );
    }

    /**
     * @return list<string>
     */
    public static function avatarModes(): array
    {
        return [
            self::AVATAR_MODE_MEDIA,
            self::AVATAR_MODE_LETTERS,
            self::AVATAR_MODE_ICON,
        ];
    }

    /**
     * @return list<string>
     */
    public static function avatarColorOptions(): array
    {
        return [
            '#F59E0B', '#FBBF24', '#F97316', '#FB7185', '#EF4444', '#DC2626',
            '#EC4899', '#D946EF', '#C026D3', '#8B5CF6', '#7C3AED', '#6366F1',
            '#4F46E5', '#3B82F6', '#2563EB', '#0EA5E9', '#0284C7', '#06B6D4',
            '#0891B2', '#14B8A6', '#0D9488', '#10B981', '#059669', '#22C55E',
            '#16A34A', '#84CC16', '#65A30D', '#A3E635', '#EAB308', '#CA8A04',
            '#A16207', '#92400E', '#78716C', '#6B7280', '#475569', '#334155',
            '#1F2937', '#111827', '#0F172A', '#4B5563', '#9CA3AF', '#CBD5E1',
            '#94A3B8', '#38BDF8', '#2DD4BF', '#4ADE80', '#FACC15', '#FDBA74',
        ];
    }

    /**
     * @return list<string>
     */
    public static function avatarIconOptions(): array
    {
        return [
            'fa-solid fa-comments', 'fa-solid fa-comment-dots', 'fa-solid fa-comment-medical',
            'fa-solid fa-bullhorn', 'fa-solid fa-flask', 'fa-solid fa-vial',
            'fa-solid fa-cube', 'fa-solid fa-cubes', 'fa-solid fa-shapes',
            'fa-solid fa-robot', 'fa-solid fa-microchip', 'fa-solid fa-memory',
            'fa-solid fa-screwdriver-wrench', 'fa-solid fa-wrench', 'fa-solid fa-hammer',
            'fa-solid fa-gears', 'fa-solid fa-gear', 'fa-solid fa-toolbox',
            'fa-solid fa-code', 'fa-solid fa-terminal', 'fa-solid fa-bug',
            'fa-solid fa-gamepad', 'fa-solid fa-dice', 'fa-solid fa-puzzle-piece',
            'fa-solid fa-satellite-dish', 'fa-solid fa-tower-broadcast', 'fa-solid fa-wifi',
            'fa-solid fa-earth-oceania', 'fa-solid fa-globe', 'fa-solid fa-compass',
            'fa-solid fa-bolt', 'fa-solid fa-fire', 'fa-solid fa-lightbulb',
            'fa-solid fa-rocket', 'fa-solid fa-plane', 'fa-solid fa-paper-plane',
            'fa-solid fa-book', 'fa-solid fa-book-open', 'fa-solid fa-bookmark',
            'fa-solid fa-graduation-cap', 'fa-solid fa-school', 'fa-solid fa-chalkboard-user',
            'fa-solid fa-compass-drafting', 'fa-solid fa-ruler-combined', 'fa-solid fa-pencil-ruler',
            'fa-solid fa-circle-nodes', 'fa-solid fa-diagram-project', 'fa-solid fa-share-nodes',
            'fa-solid fa-server', 'fa-solid fa-database', 'fa-solid fa-cloud',
            'fa-solid fa-atom', 'fa-solid fa-magnet', 'fa-solid fa-wave-square',
            'fa-solid fa-chart-line', 'fa-solid fa-chart-column', 'fa-solid fa-chart-pie',
            'fa-solid fa-trophy', 'fa-solid fa-medal', 'fa-solid fa-award',
            'fa-solid fa-wand-magic-sparkles', 'fa-solid fa-sparkles', 'fa-solid fa-wand-magic',
            'fa-solid fa-people-group', 'fa-solid fa-user-group', 'fa-solid fa-users',
            'fa-solid fa-shield-halved', 'fa-solid fa-lock', 'fa-solid fa-key',
            'fa-solid fa-star', 'fa-solid fa-heart', 'fa-solid fa-gem',
            'fa-solid fa-mountain', 'fa-solid fa-tree', 'fa-solid fa-seedling',
            'fa-solid fa-futbol', 'fa-solid fa-music', 'fa-solid fa-camera',
            'fa-solid fa-image', 'fa-solid fa-video', 'fa-solid fa-headset',
            'fa-solid fa-laptop', 'fa-solid fa-tablet-screen-button', 'fa-solid fa-mobile-screen-button',
            'fa-solid fa-shop', 'fa-solid fa-cart-shopping', 'fa-solid fa-gift',
            'forum-icon-stemcraft',
        ];
    }

    public static function normalizeAvatarMode(?string $value): string
    {
        $normalized = trim((string) $value);

        return in_array($normalized, self::avatarModes(), true) ? $normalized : self::AVATAR_MODE_LETTERS;
    }

    public static function normalizeAvatarLetters(?string $value): ?string
    {
        $normalized = strtoupper((string) Str::of((string) $value)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', ''));
        $normalized = substr($normalized, 0, 3);

        return $normalized !== '' ? $normalized : null;
    }

    public static function normalizeAvatarIconClass(?string $value): ?string
    {
        $normalized = trim((string) $value);

        return in_array($normalized, self::avatarIconOptions(), true) ? $normalized : null;
    }

    public static function normalizeAvatarBackgroundColor(?string $value): ?string
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

    public function resolvedAvatarMode(): string
    {
        $mode = self::normalizeAvatarMode((string) ($this->avatar_mode ?? ''));

        if ($mode === self::AVATAR_MODE_MEDIA && $this->hasAvatarMedia()) {
            return self::AVATAR_MODE_MEDIA;
        }

        if ($mode === self::AVATAR_MODE_ICON && $this->resolvedAvatarIconClass() !== null) {
            return self::AVATAR_MODE_ICON;
        }

        if ($this->hasAvatarMedia()) {
            return self::AVATAR_MODE_MEDIA;
        }

        if ($this->resolvedAvatarIconClass() !== null) {
            return self::AVATAR_MODE_ICON;
        }

        return self::AVATAR_MODE_LETTERS;
    }

    public function resolvedAvatarLetters(): string
    {
        $customLetters = self::normalizeAvatarLetters((string) ($this->avatar_letters ?? ''));
        if ($customLetters !== null) {
            return $customLetters;
        }

        $tokens = collect(preg_split('/[^A-Za-z0-9]+/u', (string) Str::of($this->forumDisplayName())->ascii()) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->values();

        if ($tokens->count() >= 2) {
            return strtoupper(substr($tokens[0], 0, 1).substr($tokens[1], 0, 1));
        }

        if ($tokens->count() === 1) {
            return strtoupper(substr($tokens[0], 0, min(2, strlen($tokens[0]))));
        }

        return 'U';
    }

    public function resolvedAvatarIconClass(): ?string
    {
        return self::normalizeAvatarIconClass((string) ($this->avatar_icon_class ?? ''));
    }

    public function resolvedAvatarBackgroundColor(): string
    {
        return self::normalizeAvatarBackgroundColor((string) ($this->avatar_background_color ?? '')) ?? '#374151';
    }

    public function hasAvatarMedia(): bool
    {
        return trim((string) ($this->avatar_media_name ?? '')) !== '';
    }

    public function avatarImageUrl(): ?string
    {
        if (! $this->hasAvatarMedia()) {
            return null;
        }

        $avatarMedia = $this->avatarMedia;
        if (! $avatarMedia instanceof Media) {
            return null;
        }

        return $avatarMedia->thumbnail;
    }

    public function shouldRenderAvatarImage(): bool
    {
        return $this->resolvedAvatarMode() === self::AVATAR_MODE_MEDIA && $this->avatarImageUrl() !== null;
    }

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function getSubscribedAttribute()
    {
        $email = trim((string) ($this->email ?? ''));
        if ($email === '') {
            return false;
        }

        return EmailSubscriptions::where('email', $this->email)
            ->whereNotNull('confirmed')
            ->exists();
    }

    public function setSubscribedAttribute($value)
    {
        $email = trim((string) ($this->email ?? ''));
        if ($email === '') {
            return;
        }

        if ($value) {
            $subscription = EmailSubscriptions::where('email', $email)->first();
            if ($subscription) {
                if ($subscription->confirmed === null) {
                    $subscription->update(['confirmed' => now()]);
                    $subscription->save();
                }
            } else {
                EmailSubscriptions::Create([
                    'email' => $email,
                    'confirmed' => now(),
                ]);
            }
        } else {
            EmailSubscriptions::where('email', $email)->delete();
        }
    }

    public function getEmailUpdatePendingAttribute()
    {
        /** @var Token|null $emailUpdate */
        $emailUpdate = $this->tokens()->where('type', 'email-update')->where('expires_at', '>', now())->first();

        return $emailUpdate ? $emailUpdate->data['email'] : null;
    }

    public function isAdmin(): bool
    {
        return $this->hasGroup('admin');
    }

    public function isChildAccount(): bool
    {
        return (string) ($this->parent_user_id ?? '') !== '';
    }

    public function isAnonymized(): bool
    {
        return $this->anonymized_at !== null;
    }

    public function canReceiveEmail(): bool
    {
        return trim((string) ($this->email ?? '')) !== '';
    }

    public function canUsePasswordLogin(): bool
    {
        if ($this->isAnonymized() || trim((string) ($this->password ?? '')) === '') {
            return false;
        }

        if ($this->isChildAccount()) {
            return true;
        }

        return $this->email_verified_at !== null;
    }

    public function canUseEmailLogin(): bool
    {
        return ! $this->isAnonymized()
            && ! $this->isChildAccount()
            && $this->email_verified_at !== null
            && $this->canReceiveEmail();
    }

    public function isFullAccount(): bool
    {
        return ! $this->isChildAccount() && ! $this->isAnonymized();
    }

    public function canPurchaseOrBook(): bool
    {
        return $this->isFullAccount();
    }

    public function canManageChildAccount(self $child): bool
    {
        return ! $this->isChildAccount()
            && ! $this->isAnonymized()
            && (string) $child->parent_user_id === (string) $this->id;
    }

    public function canCreateForumTopics(): bool
    {
        return ! $this->isChildAccount() || (bool) $this->child_can_create_forum_topics;
    }

    public function canReplyInForum(): bool
    {
        return ! $this->isChildAccount() || (bool) $this->child_can_reply_in_forum;
    }

    public function childForumTopicRequiresApproval(): bool
    {
        return $this->isChildAccount() && (bool) $this->child_forum_topic_requires_approval;
    }

    public function childForumReplyRequiresApproval(): bool
    {
        return $this->isChildAccount() && (bool) $this->child_forum_reply_requires_approval;
    }

    public function parentShouldBeNotifiedOnForumTopics(): bool
    {
        return $this->isChildAccount() && (bool) $this->child_parent_notified_on_forum_topics;
    }

    public function parentShouldBeNotifiedOnForumReplies(): bool
    {
        return $this->isChildAccount() && (bool) $this->child_parent_notified_on_forum_replies;
    }

    public function canEditAvatar(): bool
    {
        return ! $this->isChildAccount() || (bool) $this->child_can_select_avatar_media;
    }

    public function canSelectAvatarMedia(): bool
    {
        return $this->canEditAvatar();
    }

    public function canUseAvatarCamera(): bool
    {
        return $this->canEditAvatar() && (! $this->isChildAccount() || (bool) $this->child_can_use_avatar_camera);
    }

    public function hasMinecraftAccess(): bool
    {
        return $this->hasGroup('minecraft');
    }

    public function hasLinkedMinecraftAccounts(): bool
    {
        return $this->minecraftAccounts()->exists();
    }

    public function canViewMinecraftPage(): bool
    {
        return $this->hasMinecraftAccess() || $this->hasLinkedMinecraftAccounts();
    }

    public function canManageMinecraftAccounts(): bool
    {
        return ! $this->isChildAccount();
    }

    public function canCreateMinecraftAccounts(): bool
    {
        return $this->hasMinecraftAccess() || $this->hasGroup('admin') || $this->hasGroup('minecraft-org');
    }

    public function canAccessMinecraftPage(): bool
    {
        return $this->canManageMinecraftAccounts() || $this->canViewMinecraftPage();
    }

    public function canJoinClassSession(ClassSession $classSession): bool
    {
        return $this->isAdmin()
            || $classSession->canJoin($this);
    }

    public function canManageClassSession(ClassSession $classSession): bool
    {
        return $this->isAdmin()
            || $classSession->canManage($this);
    }

    public function classroomParticipantIdentity(ClassSession $classSession): string
    {
        return 'class-'.$classSession->id.'-user-'.$this->id;
    }

    public static function normalizeUsername(string $value): string
    {
        $normalized = Str::of($value)
            ->lower()
            ->replaceMatches('/[^a-z0-9._-]+/', '-')
            ->replaceMatches('/[-_.]{2,}/', '-')
            ->trim('-_.')
            ->value();

        return substr($normalized, 0, 32);
    }

    public static function containsRestrictedUsernameTerm(string $username): bool
    {
        $tokens = collect(preg_split('/[._-]+/', static::normalizeUsername($username)) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '')
            ->values();

        if ($tokens->isEmpty()) {
            return false;
        }

        $restrictedTerms = static::restrictedUsernameTerms();

        foreach ($restrictedTerms as $term) {
            if ($tokens->contains($term)) {
                return true;
            }
        }

        return false;
    }

    public static function generateUniqueUsernameFromEmail(string $email, ?string $ignoreUserId = null, bool $allowRestrictedTerms = false): string
    {
        $localPart = trim((string) Str::before($email, '@'));
        $base = static::normalizeUsername($localPart);

        if ($base === '') {
            $base = 'member';
        }

        if (! $allowRestrictedTerms && static::containsRestrictedUsernameTerm($base)) {
            $base = static::normalizeUsername(self::stripRestrictedUsernameTerms($base));
        }

        if ($base === '' || (! $allowRestrictedTerms && static::containsRestrictedUsernameTerm($base))) {
            $base = 'member';
        }

        return static::generateUniqueUsername($base, $ignoreUserId, $allowRestrictedTerms);
    }

    public static function generateUniqueUsername(string $requestedUsername, ?string $ignoreUserId = null, bool $allowRestrictedTerms = false): string
    {
        $base = static::normalizeUsername($requestedUsername);
        if ($base === '' || (! $allowRestrictedTerms && static::containsRestrictedUsernameTerm($base))) {
            $base = 'member';
        }

        $candidate = $base;
        $counter = 1;

        while (static::query()
            ->when($ignoreUserId !== null && $ignoreUserId !== '', fn ($query) => $query->where('id', '!=', $ignoreUserId))
            ->where('username', $candidate)
            ->exists()) {
            $suffix = (string) $counter++;
            $truncatedBase = substr($base, 0, max(1, 32 - strlen($suffix)));
            $candidate = $truncatedBase.$suffix;
        }

        return $candidate;
    }

    public static function ensureUniqueUsername(string $requestedUsername, string $email, bool $allowRestrictedTerms, ?string $ignoreUserId = null): string
    {
        $normalized = static::normalizeUsername($requestedUsername);
        if ($normalized === '') {
            return static::generateUniqueUsernameFromEmail($email, $ignoreUserId, $allowRestrictedTerms);
        }

        return static::generateUniqueUsername($normalized, $ignoreUserId, $allowRestrictedTerms);
    }

    public static function restrictedUsernameTerms(): array
    {
        $default = SiteOption::defaultValue('users.restricted-usernames')
            ?? 'stemcraft, stemmechanics, stemmech, admin, administrator, staff, mod, moderator, owner, support';

        $rawValue = $default;
        if (Schema::hasTable('site_options')) {
            $rawValue = SiteOption::value('users.restricted-usernames', $default) ?? $default;
        }

        return collect(explode(',', strtolower($rawValue)))
            ->map(fn ($value) => static::normalizeUsername((string) $value))
            ->filter(fn ($value) => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    private static function stripRestrictedUsernameTerms(string $username): string
    {
        $tokens = collect(preg_split('/[._-]+/', static::normalizeUsername($username)) ?: [])
            ->map(fn ($token) => trim((string) $token))
            ->filter(fn ($token) => $token !== '');

        $remainingTokens = $tokens
            ->reject(fn ($token) => in_array($token, static::restrictedUsernameTerms(), true))
            ->values()
            ->all();

        return implode('-', $remainingTokens);
    }

    /**
     * @return HasMany<UserGroup, $this>
     */
    public function groups(): HasMany
    {
        return $this->hasMany(UserGroup::class);
    }

    public function groupSlugs(): array
    {
        if ($this->relationLoaded('groups')) {
            return $this->groups
                ->pluck('slug')
                ->map(fn ($slug) => (string) $slug)
                ->filter(fn ($slug) => $slug !== '')
                ->unique()
                ->values()
                ->all();
        }

        return $this->groups()
            ->orderBy('slug')
            ->pluck('slug')
            ->map(fn ($slug) => (string) $slug)
            ->filter(fn ($slug) => $slug !== '')
            ->unique()
            ->values()
            ->all();
    }

    public function hasGroup(string $slug): bool
    {
        $normalized = UserGroup::normalizeSlug($slug);
        if ($normalized === '') {
            return false;
        }

        if ($this->relationLoaded('groups')) {
            return $this->groups->contains(fn (UserGroup $group) => (string) $group->slug === $normalized);
        }

        return $this->groups()
            ->where('slug', $normalized)
            ->exists();
    }

    public function backupCodes()
    {
        return $this->hasMany(UserBackupCode::class);
    }

    public function generateBackupCodes()
    {
        $this->backupCodes()->delete();
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $code = strtoupper(bin2hex(random_bytes(4)));
            $codes[] = $code;

            UserBackupCode::create([
                'user_id' => $this->id,
                'code' => $code,
            ]);
        }

        return $codes;
    }

    public function verifyBackupCode($code)
    {
        $backupCodes = $this->backupCodes()->get();
        foreach ($backupCodes as $backupCode) {
            if (Hash::check($code, $backupCode->code)) {
                $backupCode->delete();

                return true;
            }
        }

        return false;
    }
}
