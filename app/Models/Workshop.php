<?php

namespace App\Models;

use App\Traits\HasFiles;
use App\Traits\Slug;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Workshop extends Model
{
    use HasFactory, Slug, HasFiles;

    protected $fillable = [
        'title',
        'content',
        'starts_at',
        'ends_at',
        'publish_at',
        'closes_at',
        'status',
        'price',
        'ages',
        'registration',
        'registration_data',
        'location_id',
        'user_id',
        'hero_media_name'
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'publish_at' => 'datetime',
        'closes_at' => 'datetime',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function hero()
    {
        return $this->belongsTo(Media::class, 'hero_media_name');
    }

    public function location()
    {
        return $this->belongsTo(Location::class, 'location_id');
    }
}
