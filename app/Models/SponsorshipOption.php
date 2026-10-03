<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SponsorshipOption extends Model
{
    public const CHECKOUT_GROUP_COMMUNITY_SUPPORT = 'coffee';
    public const CHECKOUT_GROUP_BUSINESS = 'business';
    public const CHECKOUT_GROUP_BOTH = 'both';

    protected $fillable = [
        'project_id', 'label', 'additional_benefits', 'checkout_group', 'frequency', 'amount',
        'recognition_enabled', 'enabled', 'sort_order',
    ];

    protected $casts = ['amount' => 'decimal:2', 'recognition_enabled' => 'boolean', 'enabled' => 'boolean', 'sort_order' => 'integer'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SponsorshipProject::class, 'project_id');
    }

    public function sponsorships(): HasMany
    {
        return $this->hasMany(Sponsorship::class, 'option_id');
    }
}
