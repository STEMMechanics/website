<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipRecognitionLevel extends Model
{
    protected $fillable = ['project_id', 'name', 'minimum_total', 'sort_order', 'enabled'];

    protected $casts = ['minimum_total' => 'decimal:2', 'sort_order' => 'integer', 'enabled' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(SponsorshipProject::class, 'project_id');
    }
}
