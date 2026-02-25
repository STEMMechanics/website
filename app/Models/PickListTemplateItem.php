<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickListTemplateItem extends Model
{
    use HasFactory;

    public const TYPE_FIXED = 'fixed';
    public const TYPE_PER_PARTICIPANT = 'per_participant';

    public const TYPES = [
        self::TYPE_FIXED,
        self::TYPE_PER_PARTICIPANT,
    ];

    protected $fillable = [
        'pick_list_template_id',
        'item_name',
        'quantity_type',
        'quantity_value',
        'sort_order',
    ];

    protected $casts = [
        'quantity_value' => 'integer',
        'sort_order' => 'integer',
    ];

    /**
     * @return BelongsTo<PickListTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(PickListTemplate::class, 'pick_list_template_id');
    }

    public function computedQuantity(int $participants): int
    {
        $participants = max(0, $participants);
        $value = max(1, (int) $this->quantity_value);

        if ((string) $this->quantity_type === self::TYPE_PER_PARTICIPANT) {
            return max(0, $value * $participants);
        }

        return $value;
    }
}
