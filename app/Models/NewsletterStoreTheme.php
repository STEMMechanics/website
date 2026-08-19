<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NewsletterStoreTheme extends Model
{
    public const MATCH_TYPES = [
        'created_within' => 'Created within',
        'updated_within' => 'Updated within',
        'restocked_within' => 'Restocked within',
        'featured' => 'Featured products',
        'random' => 'Random products',
    ];

    protected $fillable = ['name', 'title', 'intro', 'category_slugs', 'match_type', 'match_days', 'is_active', 'sort_order'];

    protected $casts = [
        'category_slugs' => 'array',
        'match_days' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function matchLabel(): string
    {
        $label = self::MATCH_TYPES[$this->match_type] ?? ucfirst(str_replace('_', ' ', $this->match_type));

        return $this->match_days ? $label.' '.$this->match_days.' days' : $label;
    }
}
