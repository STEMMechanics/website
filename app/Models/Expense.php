<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
    ];

    protected $casts = [
        'paid_on' => 'date',
        'total_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
    ];

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
