<?php

namespace App\Models;

use App\Traits\HasFiles;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Quote extends Model
{
    use HasFactory, HasFiles;

    public const CONTEXT_STORE_MANUAL_SHIPPING = 'store_manual_shipping';
    public const STATUS_DRAFT = 'draft';
    public const STATUS_OPEN = 'open';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_DRAFT,
        self::STATUS_OPEN,
        self::STATUS_ACCEPTED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED,
    ];

    protected $fillable = [
        'quote_number',
        'user_id',
        'status',
        'context_type',
        'quote_date',
        'purchase_order_number',
        'title',
        'description',
        'line_items',
        'subtotal_amount',
        'gst_amount',
        'total_amount',
        'notes',
        'private_notes',
        'context_payload',
    ];

    protected $casts = [
        'quote_date' => 'date',
        'line_items' => 'array',
        'context_payload' => 'array',
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
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeVisibleToCustomer(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_DRAFT);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasManyThrough<StoreOrder, Invoice, $this>
     */
    public function storeOrders(): HasManyThrough
    {
        return $this->hasManyThrough(StoreOrder::class, Invoice::class, 'quote_id', 'invoice_id')
            ->orderByDesc('store_orders.created_at')
            ->orderByDesc('store_orders.id');
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

    public static function expireOpenQuotes(): void
    {
        static::query()
            ->where('status', self::STATUS_OPEN)
            ->whereDate('quote_date', '<=', Carbon::today()->subDays(28)->toDateString())
            ->update(['status' => self::STATUS_EXPIRED]);
    }

    public function refreshLifecycleStatus(): void
    {
        if ((string) $this->status !== self::STATUS_OPEN) {
            return;
        }

        if (! $this->quote_date instanceof Carbon) {
            return;
        }

        if ($this->quote_date->copy()->addDays(28)->startOfDay()->gt(Carbon::today())) {
            return;
        }

        $this->status = self::STATUS_EXPIRED;
        $this->save();
    }

    public function isVisibleToCustomer(): bool
    {
        return (string) $this->status !== self::STATUS_DRAFT;
    }

    public function canCustomerRespond(): bool
    {
        return (string) $this->status === self::STATUS_OPEN && ! $this->isExpired();
    }

    public function isExpired(): bool
    {
        $expiresAt = $this->expiresAt();
        if (! $expiresAt instanceof Carbon) {
            return false;
        }

        return $expiresAt->isPast();
    }

    public function acceptanceCreatesOrder(): bool
    {
        return (bool) data_get($this->context_payload, 'acceptance.creates_order', false)
            && $this->hasStoreProductLines();
    }

    public function acceptanceEmailsInvoice(): bool
    {
        return (bool) data_get($this->context_payload, 'acceptance.emails_invoice', false);
    }

    public function isStoreQuote(): bool
    {
        return (string) ($this->context_type ?? '') === self::CONTEXT_STORE_MANUAL_SHIPPING;
    }

    public function requiresAcceptancePayment(): bool
    {
        return $this->isStoreQuote()
            && ($this->acceptanceCreatesOrder() || $this->acceptanceEmailsInvoice());
    }

    public function expiresAt(): ?Carbon
    {
        if (! $this->quote_date instanceof Carbon) {
            return null;
        }

        return $this->quote_date->copy()->addDays(28)->endOfDay();
    }

    public function hasStoreProductLines(): bool
    {
        $items = is_array($this->line_items ?? null) ? array_values($this->line_items) : [];

        foreach ($items as $item) {
            if (! is_array($item) || (string) ($item['kind'] ?? 'custom') !== 'product') {
                continue;
            }

            $productId = (int) data_get($item, 'store_context.product_id', 0);
            if ($productId > 0) {
                return true;
            }
        }

        return false;
    }

    public function getAcceptanceCreatesOrderAttribute(mixed $value): bool
    {
        return (bool) data_get($this->context_payload, 'acceptance.creates_order', $value ?? false);
    }

    public function setAcceptanceCreatesOrderAttribute(mixed $value): void
    {
        $context = is_array($this->context_payload ?? null) ? $this->context_payload : [];
        data_set($context, 'acceptance.creates_order', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        $this->context_payload = $context;
    }

    public function getAcceptanceEmailsInvoiceAttribute(mixed $value): bool
    {
        return (bool) data_get($this->context_payload, 'acceptance.emails_invoice', $value ?? false);
    }

    public function setAcceptanceEmailsInvoiceAttribute(mixed $value): void
    {
        $context = is_array($this->context_payload ?? null) ? $this->context_payload : [];
        data_set($context, 'acceptance.emails_invoice', filter_var($value, FILTER_VALIDATE_BOOLEAN));
        $this->context_payload = $context;
    }

    public function getAcceptedAtAttribute(mixed $value): ?Carbon
    {
        $stored = data_get($this->context_payload, 'response.accepted_at', $value);
        if ($stored === null || trim((string) $stored) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setAcceptedAtAttribute(mixed $value): void
    {
        $context = is_array($this->context_payload ?? null) ? $this->context_payload : [];
        data_set($context, 'response.accepted_at', $value instanceof Carbon ? $value->toDateTimeString() : (is_string($value) ? trim($value) : null));
        $this->context_payload = $context;
    }

    public function getCancelledAtAttribute(mixed $value): ?Carbon
    {
        $stored = data_get($this->context_payload, 'response.cancelled_at', $value);
        if ($stored === null || trim((string) $stored) === '') {
            return null;
        }

        try {
            return Carbon::parse((string) $stored);
        } catch (\Throwable) {
            return null;
        }
    }

    public function setCancelledAtAttribute(mixed $value): void
    {
        $context = is_array($this->context_payload ?? null) ? $this->context_payload : [];
        data_set($context, 'response.cancelled_at', $value instanceof Carbon ? $value->toDateTimeString() : (is_string($value) ? trim($value) : null));
        $this->context_payload = $context;
    }

    public function setAcceptanceSettings(bool $createsOrder, bool $emailsInvoice): void
    {
        $context = is_array($this->context_payload ?? null) ? $this->context_payload : [];
        data_set($context, 'acceptance.creates_order', $createsOrder);
        data_set($context, 'acceptance.emails_invoice', $emailsInvoice);
        $this->context_payload = $context;
    }

    public function markAccepted(?Carbon $when = null): void
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->accepted_at = $when ?? now();
        $this->cancelled_at = null;
    }

    public function markCancelled(?Carbon $when = null): void
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelled_at = $when ?? now();
        $this->accepted_at = null;
    }

    public function statusLabel(): string
    {
        return self::statusLabelFor((string) $this->status);
    }

    public static function statusLabelFor(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_OPEN => 'Open',
            self::STATUS_ACCEPTED => 'Accepted',
            self::STATUS_CANCELLED => 'Cancelled',
            self::STATUS_EXPIRED => 'Expired',
            default => str($status)->headline()->toString(),
        };
    }

    public static function statusBadgeToneFor(string $status): string
    {
        return match ($status) {
            self::STATUS_DRAFT => 'gray',
            self::STATUS_OPEN => 'warning',
            self::STATUS_ACCEPTED => 'success',
            self::STATUS_CANCELLED => 'danger',
            self::STATUS_EXPIRED => 'slate',
            default => 'gray',
        };
    }

    public function statusBadgeTone(): string
    {
        return self::statusBadgeToneFor((string) $this->status);
    }

    public function getRouteKeyName(): string
    {
        return 'quote_number';
    }
}
