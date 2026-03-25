<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WorkshopTicketEmail extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_QUEUED = 'queued';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'workshop_id',
        'ticket_ids',
        'invoice_id',
        'payment_id',
        'recipient_email',
        'recipient_name',
        'payment_method',
        'amount',
        'status',
        'queued_at',
        'failed_at',
        'error_message',
    ];

    protected $casts = [
        'ticket_ids' => 'array',
        'amount' => 'decimal:2',
        'queued_at' => 'datetime',
        'failed_at' => 'datetime',
    ];
}
