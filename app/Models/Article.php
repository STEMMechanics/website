<?php

namespace App\Models;

use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Article extends Model
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
        'slug',
        'publish_at',
        'content',
        'user_id',
        'hero'
    ];


    /**
     * Get the article user
     *
     * @return BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get all of the article's attachments.
     *
     * @return MorphMany
     */
    public function attachments(): MorphMany
    {
        return $this->morphMany(\App\Models\Attachment::class, 'attachable');
    }
}
