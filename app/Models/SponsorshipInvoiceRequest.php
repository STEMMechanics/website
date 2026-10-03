<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SponsorshipInvoiceRequest extends Model
{
    protected $fillable = ['email', 'token_hash', 'payload', 'expires_at', 'used_at', 'invoice_id'];

    protected $casts = [
        'payload' => 'encrypted:array',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $hidden = ['email', 'token_hash', 'payload'];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }
}
