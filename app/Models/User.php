<?php

namespace App\Models;

use App\Mail\LoginLink;
use App\Traits\UUID;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use PharIo\Manifest\Email;

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
        'home_address',
        'home_address2',
        'home_city',
        'home_postcode',
        'home_state',
        'home_country',
        'billing_address',
        'billing_address2',
        'billing_city',
        'billing_postcode',
        'billing_state',
        'billing_country',
        'subscribed'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
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
        });

        static::deleting(function ($user) {
            EmailSubscriptions::where('email', $user->email)->delete();
        });
    }

    public function createLoginToken($redirect = null)
    {
        // Generate a unique token
        $token = Str::random(60);

        // Store the token in the database
        DB::table('login_tokens')->insert([
            'email' => $this->email,
            'token' => $token,
            'intended_url' => $redirect,
        ]);

        return $token;
    }

    public function softDelete()
    {
        foreach ($this->fillable as $field) {
            if ($field === 'email_verified_at') {
                $this->email_verified_at = null;
            } else if ($field !== 'email') {
                $this->{$field} = '';
            }
        }

        $this->save();
    }

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

    public function emailUpdate()
    {
        return $this->hasOne(EmailUpdate::class);
    }

    public function getEmailUpdatePendingAttribute()
    {
        return $this->emailUpdate()->exists();
    }

    public function isAdmin(): bool
    {
        return $this->admin === 1;
    }
}
