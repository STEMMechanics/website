<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Payment extends Model
{
    use HasFactory;

    public const KIND_PAYMENT = 'payment';
    public const KIND_REFUND = 'refund';

    public const KINDS = [
        self::KIND_PAYMENT,
        self::KIND_REFUND,
    ];

    public const PAYMENT_METHOD_CASH = 'cash';
    public const PAYMENT_METHOD_BANK_TRANSFER = 'bank_transfer';
    public const PAYMENT_METHOD_CREDIT = 'credit';
    public const PAYMENT_METHOD_CREDIT_CARD = 'credit_card';
    public const PAYMENT_METHOD_EFTPOS = 'eftpos';
    public const PAYMENT_METHOD_OTHER = 'other';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_BANK_TRANSFER,
        self::PAYMENT_METHOD_CREDIT,
        self::PAYMENT_METHOD_CREDIT_CARD,
        self::PAYMENT_METHOD_EFTPOS,
        self::PAYMENT_METHOD_OTHER,
    ];

    protected $fillable = [
        'kind',
        'refund_of_payment_id',
        'user_id',
        'created_by',
        'received_on',
        'payment_method',
        'reference',
        'total_amount',
        'gst_amount',
        'notes',
        'gateway_provider',
        'gateway_status',
        'gateway_reference_id',
        'square_payment_id',
        'square_order_id',
        'square_location_id',
        'square_receipt_url',
        'square_card_brand',
        'square_card_last4',
        'square_paid_money_amount',
        'square_refunded_money_amount',
        'square_gateway_created_at',
        'square_gateway_updated_at',
        'square_last_event_type',
        'square_last_event_id',
        'square_last_event_at',
        'square_webhook_payload',
    ];

    protected $casts = [
        'received_on' => 'datetime',
        'total_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'square_paid_money_amount' => 'integer',
        'square_refunded_money_amount' => 'integer',
        'square_gateway_created_at' => 'datetime',
        'square_gateway_updated_at' => 'datetime',
        'square_last_event_at' => 'datetime',
        'square_webhook_payload' => 'array',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function refundOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'refund_of_payment_id');
    }

    /**
     * @return HasMany<Payment, $this>
     */
    public function refunds(): HasMany
    {
        return $this->hasMany(self::class, 'refund_of_payment_id');
    }

    /**
     * @return HasMany<InvoicePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class);
    }

    /**
     * @return BelongsToMany<Invoice, $this>
     */
    public function invoices(): BelongsToMany
    {
        return $this->belongsToMany(Invoice::class, 'invoice_payment_allocations')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    /**
     * @return HasMany<SquareWebhookEvent, $this>
     */
    public function squareWebhookEvents(): HasMany
    {
        return $this->hasMany(SquareWebhookEvent::class);
    }

    public function getSquareRemainingRefundableMoneyAttribute(): int
    {
        $paid = (int) ($this->square_paid_money_amount ?? 0);
        $refunded = (int) ($this->square_refunded_money_amount ?? 0);

        return max(0, $paid - $refunded);
    }

    public function markSquareWebhook(
        string $eventType,
        string $eventId,
        ?array $payload = null,
        ?Carbon $receivedAt = null
    ): void {
        $this->square_last_event_type = $eventType;
        $this->square_last_event_id = $eventId;
        $this->square_last_event_at = $receivedAt ?? now();
        if ($payload !== null) {
            $this->square_webhook_payload = $payload;
        }
    }

    public static function paymentMethodLabel(string $paymentMethod): string
    {
        return match ($paymentMethod) {
            self::PAYMENT_METHOD_CASH => 'Cash',
            self::PAYMENT_METHOD_BANK_TRANSFER => 'Bank Transfer',
            self::PAYMENT_METHOD_CREDIT => 'Credit',
            self::PAYMENT_METHOD_CREDIT_CARD => 'Credit Card',
            self::PAYMENT_METHOD_EFTPOS => 'EFTPOS',
            self::PAYMENT_METHOD_OTHER => 'Other',
            default => ucwords(str_replace('_', ' ', $paymentMethod)),
        };
    }

    public function isRefund(): bool
    {
        return (string) $this->kind === self::KIND_REFUND || $this->refund_of_payment_id !== null;
    }

    public function isAutoImportedSquarePos(): bool
    {
        if ($this->isRefund()) {
            return false;
        }

        $provider = strtolower(trim((string) ($this->gateway_provider ?? '')));
        $method = (string) ($this->payment_method ?? '');
        $squarePaymentId = trim((string) ($this->square_payment_id ?? ''));

        return $provider === 'square'
            && $method === self::PAYMENT_METHOD_EFTPOS
            && $squarePaymentId !== '';
    }
}
