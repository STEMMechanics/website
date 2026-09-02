<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasReceiptDocument(): bool
    {
        $path = trim((string) $this->receipt_document_path);

        return $path !== '' && Storage::disk('local')->exists($path);
    }
}
