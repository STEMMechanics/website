<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopInterest extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'user_id',
        'name',
        'email',
        'phone',
        'two_day_reminder_queued_at',
        'two_day_reminder_sent_at',
        'two_hour_reminder_queued_at',
        'two_hour_reminder_sent_at',
        'reminders_unsubscribed_at',
    ];

    protected $casts = [
        'two_day_reminder_queued_at' => 'datetime',
        'two_day_reminder_sent_at' => 'datetime',
        'two_hour_reminder_queued_at' => 'datetime',
        'two_hour_reminder_sent_at' => 'datetime',
        'reminders_unsubscribed_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<Workshop, $this>
     */
    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
