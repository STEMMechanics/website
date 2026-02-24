<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareRefundOperation extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_MANUAL_REQUIRED = 'manual_required';

    protected $fillable = [
        'invoice_id',
        'tax_adjustment_id',
        'ticket_id',
        'payment_id',
        'idempotency_key',
        'requested_cents',
        'refunded_cents',
        'square_refund_id',
        'status',
        'failure_message',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'requested_cents' => 'integer',
        'refunded_cents' => 'integer',
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function adjustmentNote(): BelongsTo
    {
        return $this->belongsTo(TaxAdjustment::class, 'tax_adjustment_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
