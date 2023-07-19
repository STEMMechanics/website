<?php

namespace App\Models;

use App\Traits\HasAttachments;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Event extends Model
{
    use HasAttachments;
    use HasFactory;
    use Uuids;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'location',
        'location_url',
        'address',
        'start_at',
        'end_at',
        'publish_at',
        'status',
        'registration_type',
        'registration_data',
        'hero',
        'content',
        'price',
        'ages',
        'open_at',
    ];


    /**
     * Get all the associated users.
     *
     * @return BelongsToMany
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_user', 'event_id', 'user_id');
    }
}
