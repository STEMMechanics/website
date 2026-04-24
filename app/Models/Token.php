<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Laravel\Passport\Token as PassportToken;

class Token extends PassportToken
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'expires_at',
    ];

    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'tokens';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'data' => 'array',
    ];

    /**
     * Indicates if the model should be timestamped.
     *
     * @var bool
     */
    public $timestamps = false;

    /**
     * Get the user that owns the token.
     *
     * @return BelongsTo<\Illuminate\Foundation\Auth\User, $this>
     */
    public function user(): BelongsTo
    {
        /** @var class-string<\Illuminate\Foundation\Auth\User> $userModel */
        $userModel = User::class;

        return $this->belongsTo($userModel, 'user_id');
    }

    /**
     * The "booted" method of the model.
     *
     * @return void
     */
    public static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()}) === true) {
                do {
                    $newToken = Str::random(48);
                } while (self::where($model->getKeyName(), $newToken)->exists());

                $model->{$model->getKeyName()} = $newToken;
            }

            if (empty($model->expires_at) === true) {
                if ((string) $model->type !== 'remember-device') {
                    $model->expires_at = now()->addMinutes(10);
                }
            }
        });
    }

}
