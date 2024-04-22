<?php

namespace App\Models;

use App\Helpers;
use App\Traits\HasFiles;
use App\Traits\Slug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use HasFactory, Slug, HasFiles;

    protected $fillable = ['title', 'content', 'user_id', 'status', 'published_at', 'hero_media_name'];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hero()
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }
}
