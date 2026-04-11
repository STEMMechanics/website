<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Quote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    /**
     * @return HasMany<TaxAdjustment, $this>
     */
    public function taxAdjustments(): HasMany
    {
        return $this->hasMany(TaxAdjustment::class)->orderByDesc('issue_date')->orderByDesc('id');
    }

    /**
     * @return HasMany<InvoicePaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(InvoicePaymentAllocation::class);
    }

    /**
     * @return BelongsToMany<Payment, $this>
     */
    public function payments(): BelongsToMany
    {
        return $this->belongsToMany(Payment::class, 'invoice_payment_allocations')
            ->withPivot('allocated_amount')
            ->withTimestamps();
    }

    /**
     * @return HasMany<Ticket, $this>
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('line_number');
    }

    /**
     * @return HasMany<StoreOrder, $this>
     */
    public function storeOrders(): HasMany
    {
        return $this->hasMany(StoreOrder::class);
    }

    /**
     * @return MorphToMany<FinanceFile, $this>
     */
    public function financeFiles(): MorphToMany
    {
        return $this->morphToMany(FinanceFile::class, 'fileable', 'finance_fileables')
            ->withPivot('collection')
            ->withTimestamps();
    }

    /**
     * @return MorphToMany<FinanceFile, $this>
     */
    public function privateFinanceFiles(): MorphToMany
    {
        return $this->financeFiles()->wherePivot('collection', 'private');
    }

    public function syncPrivateFinanceFiles(array $fileIds): void
    {
        $normalizedIds = collect($fileIds)
            ->map(fn ($id) => is_numeric($id) ? (int) $id : 0)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
        $normalizedIds = FinanceFile::query()
            ->whereIn('id', $normalizedIds)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $now = now();
        $currentIds = DB::table('finance_fileables')
            ->where('fileable_type', self::class)
            ->where('fileable_id', (string) $this->getKey())
            ->where('collection', 'private')
            ->pluck('finance_file_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $detachIds = array_values(array_diff($currentIds, $normalizedIds));
        $attachIds = array_values(array_diff($normalizedIds, $currentIds));

        if ($detachIds !== []) {
            DB::table('finance_fileables')
                ->where('fileable_type', self::class)
                ->where('fileable_id', (string) $this->getKey())
                ->where('collection', 'private')
                ->whereIn('finance_file_id', $detachIds)
                ->delete();
        }

        if ($attachIds !== []) {
            $rows = array_map(fn (int $fileId) => [
                'finance_file_id' => $fileId,
                'fileable_id' => (string) $this->getKey(),
                'fileable_type' => self::class,
                'collection' => 'private',
                'created_at' => $now,
                'updated_at' => $now,
            ], $attachIds);

            DB::table('finance_fileables')->insert($rows);
        }
    }

    public function canEditContents(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function canCancel(): bool
    {
        return $this->cancellationBlockedReason() === null;
    }

    public function cancellationBlockedReason(): ?string
    {
        if ((string) $this->status === self::STATUS_CANCELLED) {
            return 'Invoice is already cancelled.';
        }

        if (! $this->canTransitionTo(self::STATUS_CANCELLED)) {
            return 'This invoice cannot be cancelled from its current status.';
        }

        $ticketBlockReason = $this->ticketCancellationBlockedReason();
        if ($ticketBlockReason !== null) {
            return $ticketBlockReason;
        }

        $storeOrderBlockReason = $this->storeOrderCancellationBlockedReason();
        if ($storeOrderBlockReason !== null) {
            return $storeOrderBlockReason;
        }

        $taxAdjustmentBlockReason = $this->taxAdjustmentCancellationBlockedReason();
        if ($taxAdjustmentBlockReason !== null) {
            return $taxAdjustmentBlockReason;
        }

        $allocated = $this->relationLoaded('allocations')
            ? round((float) $this->allocations->sum('allocated_amount'), 2)
            : round((float) $this->allocations()->sum('allocated_amount'), 2);

        if ($allocated > 0.0001) {
            return 'Reverse/refund allocated payments before cancellation.';
        }

        return null;
    }

    public function ticketCancellationBlockedReason(): ?string
    {
        $hasTickets = $this->relationLoaded('tickets')
            ? $this->tickets->isNotEmpty()
            : $this->tickets()->exists();

        if (! $hasTickets) {
            return null;
        }

        return 'This invoice has linked tickets. Cancel the ticket instead; it creates the tax adjustment note and settles the invoice.';
    }

    public function storeOrderCancellationBlockedReason(): ?string
    {
        $hasStoreOrders = $this->relationLoaded('storeOrders')
            ? $this->storeOrders->isNotEmpty()
            : $this->storeOrders()->exists();

        if ($hasStoreOrders) {
            return 'This invoice has linked store orders. Cancel the store order instead of the invoice.';
        }

        return null;
    }

    public function taxAdjustmentCancellationBlockedReason(): ?string
    {
        $hasTaxAdjustments = $this->relationLoaded('taxAdjustments')
            ? $this->taxAdjustments->isNotEmpty()
            : $this->taxAdjustments()->exists();

        if ($hasTaxAdjustments) {
            return 'This invoice already has tax adjustment notes. Reverse the adjustment instead of cancelling the invoice.';
        }

        return null;
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

        return in_array($nextStatus, $allowed[$current], true);
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

    public function statusBadgeClass(): string
    {
        return match ((string) $this->status) {
            self::STATUS_DRAFT => 'border-gray-200 bg-gray-50 text-gray-700',
            self::STATUS_ISSUED => 'border-sky-200 bg-sky-50 text-sky-800',
            self::STATUS_SENT => 'border-amber-200 bg-amber-50 text-amber-800',
            self::STATUS_PAID => 'border-emerald-200 bg-emerald-50 text-emerald-800',
            self::STATUS_OVERDUE => 'border-rose-300 bg-rose-100 text-rose-900 ring-1 ring-rose-200 shadow-sm',
            self::STATUS_CANCELLED => 'border-slate-200 bg-slate-50 text-slate-700',
            default => 'border-gray-200 bg-gray-50 text-gray-700',
        };
    }

    public function statusBadgeTone(): string
    {
        return match ((string) $this->status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_ISSUED => 'sky',
            self::STATUS_SENT => 'warning',
            self::STATUS_PAID => 'success',
            self::STATUS_OVERDUE => 'danger',
            self::STATUS_CANCELLED => 'slate',
            default => 'gray',
        };
    }

    public function contentsSummary(int $maxItems = 3): string
    {
        if (! $this->relationLoaded('lines')) {
            $this->load('lines');
        }

        if ($this->isTicketInvoice()) {
            return $this->ticketContentsSummary($maxItems);
        }

        $items = $this->lines
            ->map(function (InvoiceLine $line): string {
                $description = trim((string) $line->description);
                if ($description !== '') {
                    return Str::limit($description, 48);
                }

                $kind = trim((string) $line->kind);
                if ($kind !== '') {
                    return Str::limit(ucfirst(str_replace('_', ' ', $kind)), 48);
                }

                return '';
            })
            ->filter()
            ->unique()
            ->values();

        if ($items->isEmpty()) {
            return 'No line items';
        }

        $summary = $items->take(max(1, $maxItems))->implode(', ');
        $remaining = $items->count() - max(1, $maxItems);

        if ($remaining > 0) {
            $summary .= ' +'.$remaining.' more';
        }

        return $summary;
    }

    public function displayStatusLabel(): string
    {
        return $this->isOverdue()
            ? self::statusLabel(self::STATUS_OVERDUE)
            : self::statusLabel((string) $this->status);
    }

    public function displayStatusBadgeClass(): string
    {
        return $this->isOverdue()
            ? 'border-rose-300 bg-rose-100 text-rose-900 ring-rose-200'
            : $this->statusBadgeClass();
    }

    public function displayStatusTone(): string
    {
        return $this->isOverdue()
            ? 'danger'
            : $this->statusBadgeTone();
    }

    public function syncPaidState(): bool
    {
        $freshInvoice = $this->fresh();
        $referenceInvoice = $freshInvoice instanceof self ? $freshInvoice : $this;
        $shouldBePaid = $referenceInvoice->outstandingAmount() <= 0.0001;
        $isPaid = (string) $this->status === self::STATUS_PAID;

        if ($shouldBePaid) {
            if (! $isPaid) {
                $this->status = self::STATUS_PAID;
                $this->save();

                return true;
            }

            return false;
        }

        if ($isPaid) {
            $this->status = self::STATUS_ISSUED;
            $this->save();
        }

        return false;
    }

    public function isOverdue(): bool
    {
        if ((float) $this->total_amount <= 0) {
            return false;
        }

        if (! $this->due_date) {
            return false;
        }

        return in_array((string) $this->status, [
            self::STATUS_ISSUED,
            self::STATUS_SENT,
            self::STATUS_OVERDUE,
        ], true) && $this->due_date->lt(today());
    }

    public static function overdueCount(): int
    {
        return static::query()
            ->where('total_amount', '>', 0)
            ->whereDate('due_date', '<', today())
            ->whereIn('status', [
                self::STATUS_ISSUED,
                self::STATUS_SENT,
                self::STATUS_OVERDUE,
            ])
            ->count();
    }

    private function ticketContentsSummary(int $maxItems = 3): string
    {
        $items = $this->lines
            ->filter(fn (InvoiceLine $line): bool => (string) $line->kind === 'ticket')
            ->map(function (InvoiceLine $line): array {
                $title = trim((string) data_get($line->details_json, 'workshop_title'));
                if ($title === '') {
                    $title = trim((string) $line->description);
                    $title = preg_replace('/\s*-\s*Ticket\b.*$/i', '', $title) ?: $title;
                }

                $title = trim((string) preg_replace('/\s+/', ' ', $title));
                if ($title === '') {
                    $title = 'Workshop ticket';
                }

                return [
                    'title' => $title,
                    'quantity' => max(1, (float) $line->quantity),
                ];
            })
            ->groupBy('title')
            ->map(function ($rows, string $title): array {
                return [
                    'title' => $title,
                    'quantity' => round((float) collect($rows)->sum('quantity'), 2),
                ];
            })
            ->values();

        if ($items->isEmpty()) {
            return 'No line items';
        }

        $summary = $items
            ->take(max(1, $maxItems))
            ->map(function (array $item): string {
                $quantity = (float) $item['quantity'];

                if ($quantity <= 1.0001) {
                    return $item['title'];
                }

                return $item['title'].' x '.$this->formatSummaryQuantity($quantity);
            })
            ->implode(', ');

        $remaining = $items->count() - max(1, $maxItems);
        if ($remaining > 0) {
            $summary .= ' +'.$remaining.' more';
        }

        return $summary;
    }

    private function formatSummaryQuantity(float $quantity): string
    {
        return floor($quantity) === $quantity
            ? (string) ((int) $quantity)
            : rtrim(rtrim(number_format($quantity, 2, '.', ''), '0'), '.');
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
                $paymentQuery->where('kind', $this->expectedSettlementKind())
                    ->where(function ($clearanceQuery): void {
                        $clearanceQuery
                            ->where('payment_method', '!=', Payment::PAYMENT_METHOD_BANK_TRANSFER)
                            ->orWhereNotNull('cleared_at');
                    });
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

    public function displayOutstandingAmount(?int $excludingCustomerPaymentId = null): float
    {
        if ((string) $this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        return $this->outstandingAmount($excludingCustomerPaymentId);
    }

    public function displayDueAmount(): float
    {
        if ((string) $this->status === self::STATUS_CANCELLED) {
            return 0.0;
        }

        return $this->dueAmount();
    }

    public function isTicketInvoice(): bool
    {
        if ($this->relationLoaded('lines')) {
            if ($this->lines->isEmpty()) {
                return false;
            }

            return $this->lines->every(fn (InvoiceLine $line) => (string) $line->kind === 'ticket');
        }

        if (! $this->lines()->exists()) {
            return false;
        }

        return ! $this->lines()->where('kind', '!=', 'ticket')->exists();
    }
}
