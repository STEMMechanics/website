<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class PickListTemplate extends Model
{
    use HasFactory, HasFiles;

    public const ATTACHMENT_COLLECTION = 'workshop_template_attachments';

    protected $fillable = [
        'name',
        'description',
        'duration',
        'participants',
        'run_sheet',
        'run_sheet_drawing_data',
        'run_sheet_canvas_data',
    ];

    /**
     * @return HasMany<PickListTemplateItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PickListTemplateItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<WorkshopTemplateTask, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(WorkshopTemplateTask::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return MorphToMany<Media, $this>
     */
    public function attachments(): MorphToMany
    {
        return $this->files(self::ATTACHMENT_COLLECTION);
    }
}
