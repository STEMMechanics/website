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

    /** @return BelongsTo<Sponsor, $this> */
    public function sponsor(): BelongsTo
    {
        return $this->belongsTo(Sponsor::class, 'sponsor_id');
    }

    /** @return BelongsTo<Sponsorship, $this> */
    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }

    /** @return BelongsTo<SponsorshipProject, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(SponsorshipProject::class, 'project_id');
    }

    /** @return BelongsTo<SponsorshipRecognitionLevel, $this> */
    public function recognitionLevel(): BelongsTo
    {
        return $this->belongsTo(SponsorshipRecognitionLevel::class, 'recognition_level_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
