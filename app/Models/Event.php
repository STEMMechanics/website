<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
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
    ];

    /**
     * Get all of the post's attachments.
     */
    public function attachments()
    {
        return $this->morphMany('App\Attachment', 'attachable');
    }
}
