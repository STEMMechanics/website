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

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, UUID;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'firstname',
        'surname',
        'company',
        'email',
        'avatar_media_name',
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
                EmailSubscriptions::where('email', $user->getOriginal('email'))->update(['email' => $user->email]);

                // remove duplicate email subscriptions, favoring those with confirmed dates
                $subscriptions = EmailSubscriptions::where('email', $user->email)->orderBy('created_at', 'asc')->get();
                $confirmed = EmailSubscriptions::where('email', $user->email)->whereNotNull('confirmed')->orderBy('confirmed', 'asc')->first();
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
            }

            if ($user->isDirty('tfa_secret')) {
                if ($user->tfa_secret === null) {
                    $user->backupCodes()->delete();
                    dispatch(new SendEmail($user->email, new UserLoginTFADisabled($user->email)))->onQueue('mail');
                } else {
                    dispatch(new SendEmail($user->email, new UserLoginTFAEnabled($user->email)))->onQueue('mail');
                }
            }
        });

        static::deleting(function ($user) {
            EmailSubscriptions::where('email', $user->email)->delete();
        });

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
        $name = '';

        if ($this->firstname || $this->surname) {
            $name = implode(' ', [$this->firstname, $this->surname]);
        } else if ((string) $this->username !== '') {
            $name = (string) $this->username;
        } else {
            $name = substr($this->email, 0, strpos($this->email, '@'));
        }

        return $name;
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

    public function createdPayments(): HasMany
    {
        return $this->hasMany(Payment::class, 'created_by');
    }

    public function getSubscribedAttribute()
    {
        return EmailSubscriptions::where('email', $this->email)
            ->whereNotNull('confirmed')
            ->exists();
    }

    public function setSubscribedAttribute($value)
    {
        if ($value) {
            $subscription = EmailSubscriptions::where('email', $this->email)->first();
            if ($subscription) {
                if ($subscription->confirmed === null) {
                    $subscription->update(['confirmed' => now()]);
                    $subscription->save();
                }
            } else {
                EmailSubscriptions::Create([
                    'email' => $this->email,
                    'confirmed' => now(),
                ]);
            }
        } else {
            EmailSubscriptions::where('email', $this->email)->delete();
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

    public function hasMinecraftAccess(): bool
    {
        return $this->hasGroup('minecraft');
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
