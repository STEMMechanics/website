<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Attachment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'media_id',
        'private',
    ];

    /**
     * The default attributes.
     *
     * @var string[]
     */
    protected $attributes = [
        'private' => 'false',
    ];


    /**
     * Get attachments attachable
     *
     * @return MorphTo
     */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the media for this attachment.
     *
     * @return BelongsTo
     */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }
}
