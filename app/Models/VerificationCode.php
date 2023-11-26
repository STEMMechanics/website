<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VerificationCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'type',
    ];

    /**
     * Boot function from Laravel.
     *
     * @return void
     */
    protected static function boot(): void
    {
        parent::boot();
        static::creating(function ($model) {
            VerificationCode::clearExpired();

            if(empty($model->{'user_id'}) === false && empty($model->{'user_id'}) === false) {
                VerificationCode::where('user_id', $model->{'user_id'})->where('type', $model->{'type'})->delete();
            }

            if (empty($model->{'code'}) === true) {
                while (true) {
                    $code = random_int(100000, 999999);
                    if (VerificationCode::where('code', $code)->count() === 0) {
                        $model->{'code'} = $code;
                        break;
                    }
                }
            }
        });
    }

    /**
     * Clear expired user codes
     *
     * @return void
     */
    public static function clearExpired(): void
    {
        VerificationCode::where('updated_at', '<=', now()->subDays(5))->delete();
    }

    /**
     * Get associated user
     *
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
