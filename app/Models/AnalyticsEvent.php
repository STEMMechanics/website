<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalyticsEvent extends Model
{
    use HasFactory;

    public const TYPE_PAGE_VIEW = 'page_view';

    public const TYPE_REGISTRATION_CLICK = 'registration_click';

    public const TYPE_SEARCH = 'search';

    public const TYPE_RECOMMENDATION_IMPRESSION = 'recommendation_view';

    public const TYPE_RECOMMENDATION_CLICK = 'recommendation_click';

    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'session_token',
        'is_session_entry',
        'visitor_hash',
        'path',
        'landing_path',
        'route_name',
        'workshop_id',
        'source_workshop_id',
        'recommendation_placement',
        'search_term',
        'referrer_host',
        'acquisition_source',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'http_method',
        'created_at',
    ];

    protected $casts = [
        'is_session_entry' => 'boolean',
        'created_at' => 'datetime',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class, 'workshop_id');
    }
}
