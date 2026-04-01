<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use DateTimeInterface;

class Payment extends Model
{
    use HasFactory;

    private const SQUARE_META_FIELDS = [
        'square_payment_id' => 'square_payment_id',
        'square_order_id' => 'square_order_id',
        'square_location_id' => 'square_location_id',
        'square_receipt_url' => 'square_receipt_url',
        'square_card_brand' => 'square_card_brand',
        'square_card_last4' => 'square_card_last4',
        'square_paid_money_amount' => 'square_paid_money_amount',
        'square_refunded_money_amount' => 'square_refunded_money_amount',
        'square_gateway_created_at' => 'square_gateway_created_at',
        'square_gateway_updated_at' => 'square_gateway_updated_at',
        'square_last_event_type' => 'square_last_event_type',
        'square_last_event_id' => 'square_last_event_id',
        'square_last_event_at' => 'square_last_event_at',
        'square_webhook_payload' => 'square_webhook_payload',
    ];

    private const SQUARE_META_INT_FIELDS = [
        'square_paid_money_amount',
        'square_refunded_money_amount',
    ];

    private const SQUARE_META_DATETIME_FIELDS = [
        'square_gateway_created_at',
        'square_gateway_updated_at',
        'square_last_event_at',
    ];

    private const SQUARE_META_ARRAY_FIELDS = [
        'square_webhook_payload',
    ];

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
        'cleared_at',
        'gateway_provider',
        'gateway_status',
        'gateway_reference_id',
        'square_integration_meta',
    ];

    protected $casts = [
        'received_on' => 'datetime',
        'cleared_at' => 'datetime',
        'total_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'square_integration_meta' => 'array',
    ];

    public function getAttribute($key): mixed
    {
        if (array_key_exists($key, self::SQUARE_META_FIELDS)) {
            return $this->getSquareMetaAttributeValue($key);
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value): static
    {
        if (array_key_exists($key, self::SQUARE_META_FIELDS)) {
            $this->setSquareMetaAttributeValue($key, $value);

            return $this;
        }

        return parent::setAttribute($key, $value);
    }

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
            $this->setAttribute('square_webhook_payload', $payload);
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

    public function isPendingBankTransfer(): bool
    {
        return (string) ($this->kind ?? self::KIND_PAYMENT) === self::KIND_PAYMENT
            && (string) ($this->payment_method ?? '') === self::PAYMENT_METHOD_BANK_TRANSFER
            && $this->cleared_at === null;
    }

    public function clearanceStatusLabel(): string
    {
        return $this->isPendingBankTransfer() ? 'Pending clearance' : 'Cleared';
    }

    public function clearanceStatusClass(): string
    {
        return $this->isPendingBankTransfer()
            ? 'border-amber-200 bg-amber-50 text-amber-800'
            : 'border-emerald-200 bg-emerald-50 text-emerald-800';
    }

    public function receiptCanBeEmailed(): bool
    {
        return ! $this->isPendingBankTransfer();
    }

    public function scopePendingBankTransfers(Builder $query): Builder
    {
        return $query
            ->whereNull('refund_of_payment_id')
            ->where('kind', self::KIND_PAYMENT)
            ->where('payment_method', self::PAYMENT_METHOD_BANK_TRANSFER)
            ->whereNull('cleared_at');
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

    private function getSquareMetaAttributeValue(string $field): mixed
    {
        $meta = parent::getAttribute('square_integration_meta');
        if (! is_array($meta)) {
            return null;
        }

        $metaKey = self::SQUARE_META_FIELDS[$field];
        if (! array_key_exists($metaKey, $meta)) {
            return null;
        }

        $value = $meta[$metaKey];

        if (in_array($field, self::SQUARE_META_INT_FIELDS, true)) {
            return $value === null || $value === '' ? null : (int) $value;
        }

        if (in_array($field, self::SQUARE_META_DATETIME_FIELDS, true)) {
            $raw = trim((string) $value);
            if ($raw === '') {
                return null;
            }

            try {
                return Carbon::parse($raw);
            } catch (\Throwable) {
                return null;
            }
        }

        if (in_array($field, self::SQUARE_META_ARRAY_FIELDS, true)) {
            return is_array($value) ? $value : null;
        }

        return $value === null ? null : (string) $value;
    }

    private function setSquareMetaAttributeValue(string $field, mixed $value): void
    {
        $meta = parent::getAttribute('square_integration_meta');
        if (! is_array($meta)) {
            $meta = [];
        }

        $metaKey = self::SQUARE_META_FIELDS[$field];
        $normalized = $this->normalizeSquareMetaValue($field, $value);

        if ($normalized === null) {
            unset($meta[$metaKey]);
        } else {
            $meta[$metaKey] = $normalized;
        }

        parent::setAttribute('square_integration_meta', $meta);
    }

    private function normalizeSquareMetaValue(string $field, mixed $value): mixed
    {
        if (in_array($field, self::SQUARE_META_INT_FIELDS, true)) {
            if ($value === null || $value === '') {
                return null;
            }

            return (int) $value;
        }

        if (in_array($field, self::SQUARE_META_DATETIME_FIELDS, true)) {
            if ($value === null || $value === '') {
                return null;
            }

            if ($value instanceof DateTimeInterface) {
                return Carbon::instance($value)->setTimezone((string) config('app.timezone'))->toDateTimeString();
            }

            try {
                return Carbon::parse((string) $value)->setTimezone((string) config('app.timezone'))->toDateTimeString();
            } catch (\Throwable) {
                return null;
            }
        }

        if (in_array($field, self::SQUARE_META_ARRAY_FIELDS, true)) {
            return is_array($value) && $value !== [] ? $value : null;
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        return $value === null || $value === '' ? null : (string) $value;
    }
}
