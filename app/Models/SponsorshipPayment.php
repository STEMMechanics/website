<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'sponsorship_id', 'invoice_id', 'payment_id', 'square_payment_id', 'square_order_id', 'square_invoice_id',
        'status', 'subtotal', 'gst_amount', 'total_amount', 'tax_rate', 'tax_treatment', 'tax_code',
        'sponsor_country', 'billing_period', 'attempt_number', 'square_idempotency_key',
        'invoice_number', 'invoice_pdf_path', 'paid_at', 'emailed_at', 'receipt_email_enabled',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2', 'gst_amount' => 'decimal:2', 'total_amount' => 'decimal:2', 'tax_rate' => 'decimal:4',
        'paid_at' => 'datetime', 'emailed_at' => 'datetime', 'receipt_email_enabled' => 'boolean',
        'billing_period' => 'date', 'attempt_number' => 'integer',
    ];

    protected $hidden = ['invoice_pdf_path', 'payment_id', 'invoice_id', 'square_payment_id', 'square_order_id', 'square_invoice_id', 'square_idempotency_key'];

    public function sponsorship(): BelongsTo
    {
        return $this->belongsTo(Sponsorship::class, 'sponsorship_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
