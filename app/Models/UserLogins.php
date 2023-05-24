<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserLogins extends Model
{
    use HasFactory;
    use Uuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'token',
        'login',
        'logout',
        'ip_address',
        'user_agent',
    ];


    /**
     * Get the file user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
