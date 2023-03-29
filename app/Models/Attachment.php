<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    ];


    /**
     * Get attachments attachable
     */
    public function attachable()
    {
        return $this->morphTo();
    }

    /**
     * Get the media for this attachment.
     */
    public function media()
    {
        return $this->belongsTo(Media::class);
    }
}