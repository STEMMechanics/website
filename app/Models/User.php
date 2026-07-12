<?php

namespace App\Models;

use App\Jobs\SendEmail;
use App\Mail\UserLoginTFADisabled;
use App\Mail\UserLoginTFAEnabled;
use App\Traits\UUID;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Laravel\Passport\Contracts\OAuthenticatable;
use Laravel\Passport\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail, OAuthenticatable
{
    use HasApiTokens, HasFactory, Notifiable, UUID;

    /**
     * @var array<string, bool>
     */
    protected static array $databaseColumnCache = [];

    public const ACCOUNT_TERMS_OPTIONS = [
        0,
        7,
        14,
        21,
        28,
    ];

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
        'password',
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
        'account_terms_days',
        'subscribed',
        'agree_tos',
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
        'account_terms_days' => 0,
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'account_terms_days' => 'integer',
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
     * @return array<int, string>
     */
    public static function accountTermsOptions(): array
    {
        return [
            0 => 'Current',
            7 => '7 days',
            14 => '14 days',
            21 => '21 days',
            28 => '28 days',
        ];
    }

    public function accountTermsDays(): int
    {
        $days = (int) ($this->account_terms_days ?? 0);

        return in_array($days, self::ACCOUNT_TERMS_OPTIONS, true) ? $days : 0;
    }

    public function hasAccountTerms(): bool
    {
        return $this->accountTermsDays() > 0;
    }

    public function accountTermsLabel(): string
    {
        $days = $this->accountTermsDays();

        return $days <= 0 ? 'Current' : $days.' days';
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

    public function media(): HasMany
    {
        return $this->hasMany(Media::class);
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

        return $this->email_verified_at !== null;
    }

    public function canUseEmailLogin(): bool
    {
        return ! $this->isAnonymized()
            && $this->email_verified_at !== null
            && $this->canReceiveEmail();
    }

    public function isFullAccount(): bool
    {
        return ! $this->isAnonymized();
    }

    public function canPurchaseOrBook(): bool
    {
        return $this->isFullAccount();
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
        return true;
    }

    public function canCreateMinecraftAccounts(): bool
    {
        return $this->hasMinecraftAccess() || $this->hasGroup('admin') || $this->hasGroup('minecraft-org');
    }

    public function canAccessMinecraftPage(): bool
    {
        return $this->canManageMinecraftAccounts() || $this->canViewMinecraftPage();
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
