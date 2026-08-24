<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopTemplateTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'pick_list_template_id',
        'name',
        'notes',
        'subtasks',
        'reminder_enabled',
        'reminder_offset_days',
        'reminder_time',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'reminder_enabled' => 'boolean',
        'reminder_offset_days' => 'integer',
        'subtasks' => 'array',
    ];

    /**
     * @return BelongsTo<PickListTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PickListTemplate::class, 'pick_list_template_id');
    }
}
