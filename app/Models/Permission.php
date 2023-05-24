<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;
    use Uuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'permission',
        'user',
    ];


    /**
     * Get the User associated with this model
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
