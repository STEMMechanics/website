<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopRunSheetTask extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'blueprint_task_id',
        'name',
        'notes',
        'subtasks',
        'reminder_enabled',
        'reminder_offset_days',
        'reminder_time',
        'sort_order',
    ];

    protected $casts = [
        'subtasks' => 'array',
        'reminder_enabled' => 'boolean',
        'reminder_offset_days' => 'integer',
        'sort_order' => 'integer',
    ];

    /** @return BelongsTo<Workshop, $this> */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /** @return BelongsTo<WorkshopTemplateTask, $this> */
    public function blueprintTask(): BelongsTo
    {
        return $this->belongsTo(WorkshopTemplateTask::class, 'blueprint_task_id');
    }
}
