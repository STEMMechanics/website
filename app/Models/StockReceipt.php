<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceipt extends Model
{
    use HasFactory;

    protected $fillable = ['supplier_id', 'expense_id', 'created_by', 'reference', 'received_at', 'currency', 'exchange_rate', 'freight_ex_tax', 'notes'];

    protected $casts = [
        'received_at' => 'datetime',
        'exchange_rate' => 'decimal:6',
        'freight_ex_tax' => 'decimal:4',
    ];

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_id');
    }

    /** @return BelongsTo<Expense, $this> */
    public function expense(): BelongsTo
    {
        return $this->belongsTo(Expense::class, 'expense_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<StockReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(StockReceiptLine::class);
    }
}
