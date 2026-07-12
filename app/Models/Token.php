<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Token extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'type',
        'data',
        'expires_at',
    ];

    protected $table = 'tokens';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'data' => 'array',
    ];

    public $timestamps = false;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            if (empty($model->{$model->getKeyName()})) {
                do {
                    $newToken = Str::random(48);
                } while (self::query()->where($model->getKeyName(), $newToken)->exists());

                $model->{$model->getKeyName()} = $newToken;
            }

            if (empty($model->expires_at) && (string) $model->type !== 'remember-device') {
                $model->expires_at = now()->addMinutes(10);
            }
        });
    }
}
