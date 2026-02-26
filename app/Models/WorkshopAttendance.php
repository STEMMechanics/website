<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkshopAttendance extends Model
{
    use HasFactory;

    protected $fillable = [
        'workshop_id',
        'ticket_id',
        'user_id',
        'created_by',
        'source',
        'child_name',
        'firstname',
        'surname',
        'guardian_name',
        'email',
        'phone',
        'media_consent',
        'attended_at',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
        'media_consent' => 'boolean',
    ];

    public function workshop(): BelongsTo
    {
        return $this->belongsTo(Workshop::class);
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
