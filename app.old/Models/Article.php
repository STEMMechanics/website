<?php

namespace App\Models;

use App\Traits\HasAttachments;
use App\Traits\HasGallery;
use App\Traits\Uuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Article extends Model
{
    use HasFactory;
    use Uuids;
    use HasGallery;
    use HasAttachments;

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
     * @return Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
