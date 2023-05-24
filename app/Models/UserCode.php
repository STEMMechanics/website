<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'action',
        'user_id',
        'data',
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
            UserCode::clearExpired();

            if (empty($model->{'code'}) === true) {
                while (true) {
                    $code = random_int(100000, 999999);
                    if (UserCode::where('code', $code)->count() === 0) {
                        $model->{'code'} = $code;
                        break;
                    }
                }
            }
        });
    }

    /**
     * Generate new code
     *
     * @return void
     */
    public function regenerate(): void
    {
        while (true) {
            $code = random_int(100000, 999999);
            if (UserCode::where('code', $code)->count() === 0) {
                $this->code = $code;
                break;
            }
        }
    }

    /**
     * Clear expired user codes
     *
     * @return void
     */
    public static function clearExpired(): void
    {
        UserCode::where('updated_at', '<=', now()->subDays(5))->delete();
    }

    /**
     * Get associated user
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
