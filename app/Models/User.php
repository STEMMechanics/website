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

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable, UUID;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'admin',
        'firstname',
        'surname',
        'email',
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
        'tfa_secret',
        'agree_tos',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'tfa_secret'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    protected $appends = [
        'subscribed',
        'email_update_pending'
    ];

    public static function boot()
    {
        parent::boot();

        static::updating(function ($user) {
            if ($user->isDirty('email')) {
                EmailSubscriptions::where('email', $user->getOriginal('email'))->update(['email' => $user->email]);

                // remove duplicate email subscriptions, favoring those with confirmed dates
                $subscriptions = EmailSubscriptions::where('email', $user->email)->orderBy('created_at', 'asc')->get();
                $confirmed = EmailSubscriptions::where('email', $user->email)->whereNotNull('confirmed')->orderBy('confirmed', 'asc')->first();
                if($subscriptions->count() > 1) {
                    // if there is a confirmed, then delete all the others
                    if($confirmed) {
                        $subscriptions->each(function($subscription) use ($confirmed) {
                            if($subscription->id !== $confirmed->id) {
                                $subscription->delete();
                            }
                        });
                    } else {
                        // if there is no confirmed, then delete all but the most recent
                        $subscriptions->each(function($subscription) use ($subscriptions) {
                            if($subscription->id !== $subscriptions->last()->id) {
                                $subscription->delete();
                            }
                        });
                    }
                }
            }

            if ($user->isDirty('tfa_secret')) {
                if($user->tfa_secret === null) {
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
     *
     * @return HasMany
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(Token::class);
    }

    /**
     * Get the calculated name of the user.
     *
     * @return string
     */
    public function getName(): string
    {
        $name = '';

        if($this->firstname || $this->surname) {
            $name = implode(' ', [$this->firstname, $this->surname]);
        } else {
            $name = substr($this->email, 0, strpos($this->email, '@'));
        }

        return $name;
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
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
                if($subscription->confirmed === null) {
                    $subscription->update(['confirmed' => now()]);
                    $subscription->save();
                }
            } else {
                EmailSubscriptions::Create([
                    'email' => $this->email,
                    'confirmed' => now()
                ]);
            }
        } else {
            EmailSubscriptions::where('email', $this->email)->delete();
        }
    }


    public function getEmailUpdatePendingAttribute()
    {
        $emailUpdate = $this->tokens()->where('type', 'email-update')->where('expires_at', '>', now())->first();
        return $emailUpdate ? $emailUpdate->data['email'] : null;
    }

    public function isAdmin(): bool
    {
        return $this->admin === 1;
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
