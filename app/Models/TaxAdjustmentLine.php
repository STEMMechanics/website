<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaxAdjustmentLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'tax_adjustment_id',
        'invoice_line_id',
        'line_number',
        'description',
        'notes',
        'quantity',
        'unit_price_ex_tax',
        'tax_rate',
        'line_total_ex_tax',
        'tax_amount',
        'line_total_inc_tax',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price_ex_tax' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total_ex_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total_inc_tax' => 'decimal:2',
    ];

    /**
     * @return BelongsTo<TaxAdjustment, $this>
     */
    public function taxAdjustment(): BelongsTo
    {
        return $this->belongsTo(TaxAdjustment::class);
    }

    /**
     * @return BelongsTo<InvoiceLine, $this>
     */
    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(InvoiceLine::class);
    }
}
