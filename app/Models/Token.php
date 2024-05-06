<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Token extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
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
     * The primary key for the model is incrementing.
     *
     * @var bool $incrementing
     */
    public $incrementing = false;

    /**
     * The primary key type for the model.
     *
     * @var string
     */
    public $keyType = 'string';

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
                $model->expires_at = now()->addMinutes(10);
            }
        });
    }

    /**
     * Get the user that the token belongs to.
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
