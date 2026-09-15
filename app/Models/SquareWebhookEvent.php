<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SquareWebhookEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'event_type',
        'payment_id',
        'payload',
        'processed_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'processed_at' => 'datetime',
    ];

    public function paymentOutcome(): ?array
    {
        $payment = data_get($this->payload, 'data.object.payment');
        if (! is_array($payment)) {
            return null;
        }
        $status = strtoupper((string) ($payment['status'] ?? data_get($payment, 'card_details.status', '')));
        if ($status === '') {
            return null;
        }
        $errors = collect(data_get($payment, 'card_details.errors', []))
            ->filter(fn ($error) => is_array($error))
            ->map(fn ($error) => ['code' => (string) ($error['code'] ?? ''), 'detail' => (string) ($error['detail'] ?? '')])
            ->values()->all();
        $declined = collect($errors)->contains(fn ($error) => str_contains($error['code'], 'DECLIN'));
        $label = match ($status) {
            'FAILED' => $declined ? 'Payment declined' : 'Payment failed',
            'COMPLETED' => 'Payment completed',
            'APPROVED', 'AUTHORIZED' => 'Payment authorised',
            'PENDING' => 'Payment pending',
            'CANCELED' => 'Payment cancelled',
            default => 'Payment '.strtolower($status),
        };

        return [
            'label' => $label,
            'tone' => match ($status) {
                'FAILED' => 'danger', 'COMPLETED' => 'success', default => 'warning'
            },
            'cvv_rejected' => data_get($payment, 'card_details.cvv_status') === 'CVV_REJECTED',
            'errors' => $errors,
        ];
    }

    public function customerPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'payment_id');
    }
}
