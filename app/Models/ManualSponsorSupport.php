<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ManualSponsorSupport extends Model
{
    protected $fillable = [
        'sponsor_id', 'sponsorship_id', 'project_id', 'recognition_level_id', 'support_method', 'support_description',
        'value_amount', 'currency', 'starts_on', 'ends_on', 'internal_note', 'created_by',
    ];

    protected $casts = [
        'value_amount' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];

    protected $hidden = [
        'sponsor_id', 'sponsorship_id', 'project_id', 'recognition_level_id', 'support_method',
        'support_description', 'value_amount', 'currency', 'starts_on', 'ends_on', 'internal_note', 'created_by',
    ];

    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'sponsor_id');
    }

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(SponsorshipProject::class, 'project_id');
    }

    public function recognitionLevel(): BelongsTo
    {
        return $this->belongsTo(SponsorshipRecognitionLevel::class, 'recognition_level_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
