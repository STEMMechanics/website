<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class MediaDownload extends Model
{
    protected $fillable = [
        'media_name',
        'user_id',
        'variant',
        'source',
    ];

    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_name', 'name');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
