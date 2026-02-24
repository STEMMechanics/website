<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TaxAdjustment extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'adjustment_number',
        'issue_date',
        'subtotal_amount',
        'gst_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'subtotal_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return HasMany<InvoicePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class);
    }

    /**
     * @return HasMany<TaxAdjustmentLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(TaxAdjustmentLine::class)->orderBy('line_number');
    }

    public function getRouteKeyName(): string
    {
        return 'adjustment_number';
    }
}
