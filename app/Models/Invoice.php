<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory, HasFiles;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_ISSUED = 'issued';
    public const STATUS_SENT = 'sent';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_ISSUED,
        self::STATUS_SENT,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'invoice_number',
        'quote_id',
        'user_id',
        'billing_name',
        'billing_email',
        'billing_phone',
        'status',
        'issue_date',
        'issued_at',
        'due_date',
        'purchase_order_number',
        'subtotal_amount',
        'gst_amount',
        'total_amount',
        'notes',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'issued_at' => 'datetime',
        'due_date' => 'date',
        'subtotal_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function taxAdjustments(): HasMany
    {
        return $this->hasMany(TaxAdjustment::class)->orderByDesc('issue_date')->orderByDesc('id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class);
    }

    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'invoice_payment_allocations')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_number');
    }

    public function canEditContents(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canTransitionTo(string $nextStatus): bool
    {
        $current = (string) $this->status;
        if ($current === $nextStatus) {
            return true;
        }

        $allowed = [
            self::STATUS_DRAFT => [self::STATUS_ISSUED, self::STATUS_SENT, self::STATUS_CANCELLED],
            self::STATUS_ISSUED => [self::STATUS_SENT, self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
            self::STATUS_SENT => [self::STATUS_PAID, self::STATUS_OVERDUE, self::STATUS_CANCELLED],
            self::STATUS_OVERDUE => [self::STATUS_PAID, self::STATUS_CANCELLED],
            self::STATUS_PAID => [self::STATUS_CANCELLED],
            self::STATUS_CANCELLED => [],
        ];

        return in_array($nextStatus, $allowed[$current] ?? [], true);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_ISSUED => 'Issued',
            self::STATUS_SENT => 'Sent',
            self::STATUS_PAID => 'Paid',
            self::STATUS_OVERDUE => 'Overdue',
            self::STATUS_CANCELLED => 'Cancelled',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    public function getRouteKeyName(): string
    {
        return 'invoice_number';
    }

    public function expectedSettlementKind(): string
    {
        return ((float) $this->total_amount) < 0
            ? Payment::KIND_REFUND
            : Payment::KIND_PAYMENT;
    }

    public function absoluteTotalAmount(): float
    {
        return abs(round((float) $this->total_amount, 2));
    }

    public function issuedAdjustmentTotalAmount(): float
    {
        if ($this->relationLoaded('taxAdjustments')) {
            return round((float) $this->taxAdjustments->sum('total_amount'), 2);
        }

        return round((float) $this->taxAdjustments()->sum('total_amount'), 2);
    }

    public function dueAmount(): float
    {
        return max(0, round((float) $this->total_amount + $this->issuedAdjustmentTotalAmount(), 2));
    }

    public function settledAmount(?int $excludingCustomerPaymentId = null): float
    {
        $query = $this->allocations()
            ->where('allocated_amount', '>', 0)
            ->whereHas('customerPayment', function ($paymentQuery): void {
                $paymentQuery->where('kind', $this->expectedSettlementKind());
            });

        if ($excludingCustomerPaymentId !== null) {
            $query->where('payment_id', '!=', $excludingCustomerPaymentId);
        }

        return round((float) $query->sum('allocated_amount'), 2);
    }

    public function outstandingAmount(?int $excludingCustomerPaymentId = null): float
    {
        return max(0, round($this->dueAmount() - $this->settledAmount($excludingCustomerPaymentId), 2));
    }
}
