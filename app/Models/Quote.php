<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, HasFiles;

    protected $fillable = [
        'quote_number',
        'user_id',
        'quote_date',
        'title',
        'description',
        'line_items',
        'subtotal_amount',
        'gst_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'line_items' => 'array',
        'subtotal_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function getRouteKeyName(): string
    {
        return 'quote_number';
    }
}
