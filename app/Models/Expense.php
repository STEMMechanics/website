<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'created_by',
        'supplier',
        'description',
        'invoice_id',
        'paid_on',
        'total_amount',
        'gst_amount',
        'receipt_document_path',
        'receipt_document_name',
        'receipt_document_text',
        'receipt_document_index_queued_at',
        'receipt_document_indexed_at',
    ];

    protected $casts = [
        'paid_on' => 'date',
        'total_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'receipt_document_index_queued_at' => 'datetime',
        'receipt_document_indexed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (Expense $expense): void {
            if (! $expense->exists || $expense->isDirty('supplier') || $expense->supplier_id === null) {
                $name = trim((string) $expense->supplier);
                $expense->supplier_id = $name === '' ? null : Supplier::forName($name)->id;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasManyThrough<StockReceiptLine, StockReceipt, $this> */
    public function stockReceiptLines(): HasManyThrough
    {
        return $this->hasManyThrough(StockReceiptLine::class, StockReceipt::class, 'expense_id', 'stock_receipt_id');
    }

    public function hasReceiptDocument(): bool
    {
        $path = trim((string) $this->receipt_document_path);

        return $path !== '' && Storage::disk('local')->exists($path);
    }
}
