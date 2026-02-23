<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class InvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'line_number',
        'kind',
        'description',
        'notes',
        'details_json',
        'quantity',
        'unit_price_ex_tax',
        'tax_rate',
        'line_total_ex_tax',
        'tax_amount',
        'line_total_inc_tax',
        'source_type',
        'source_id',
        'original_invoice_line_id',
    ];

    protected $casts = [
        'details_json' => 'array',
        'quantity' => 'decimal:2',
        'unit_price_ex_tax' => 'decimal:2',
        'tax_rate' => 'decimal:4',
        'line_total_ex_tax' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total_inc_tax' => 'decimal:2',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function originalLine(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_invoice_line_id');
    }

    public function reversalLines(): HasMany
    {
        return $this->hasMany(self::class, 'original_invoice_line_id');
    }
}
